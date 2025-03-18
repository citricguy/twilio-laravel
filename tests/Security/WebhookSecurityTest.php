<?php

namespace Citricguy\TwilioLaravel\Tests\Security;

use Citricguy\TwilioLaravel\Helpers\WebhookSignatureHelper;
use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;

/**
 * Security tests for Twilio webhook handling
 */
it('rejects requests with missing signature header', function () {
    // Enable validation
    config(['twilio-laravel.validate_webhook' => true]);
    config(['twilio-laravel.auth_token' => 'test_auth_token']);

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');

    // Post without the signature header
    $response = $this->postJson($webhookPath, [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
    ]);

    // Should get a 403 Forbidden
    $response->assertStatus(403);
    $response->assertSee('Missing Twilio signature header');
});

it('rejects requests with invalid signature', function () {
    // Enable validation
    config(['twilio-laravel.validate_webhook' => true]);
    config(['twilio-laravel.auth_token' => 'test_auth_token']);

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');

    // Post with invalid signature
    $response = $this->withHeaders([
        'X-Twilio-Signature' => 'invalid_signature_here',
    ])->postJson($webhookPath, [
        'MessageSid' => 'SM123456',
    ]);

    // Should get a 403 Forbidden
    $response->assertStatus(403);
    $response->assertSee('Invalid Twilio webhook signature');
});

it('rejects replay attacks with modified payload', function () {
    // Enable validation
    config(['twilio-laravel.validate_webhook' => true]);
    config(['twilio-laravel.auth_token' => 'test_auth_token']);

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');
    $fullUrl = 'http://localhost'.$webhookPath;

    $originalParams = ['MessageSid' => 'SM123456', 'Body' => 'Original message'];

    // Generate valid signature for original params
    $validSignature = WebhookSignatureHelper::generateValidSignature($fullUrl, $originalParams, 'test_auth_token');

    // Try to use the same signature with modified payload (simulating a replay attack)
    $modifiedParams = ['MessageSid' => 'SM123456', 'Body' => 'Modified message'];

    $response = $this->withHeaders([
        'X-Twilio-Signature' => $validSignature,
    ])->postJson($webhookPath, $modifiedParams);

    // Signature should be invalid because payload changed
    $response->assertStatus(403);
});

it('rejects requests with manipulated urls', function () {
    // Enable validation
    config(['twilio-laravel.validate_webhook' => true]);
    config(['twilio-laravel.auth_token' => 'test_auth_token']);

    // Instead of trying to mock URL changes in the test environment,
    // we'll test the middleware directly with a controlled request

    // Get webhook path from config and create URLs
    $webhookPath = config('twilio-laravel.webhook_path');
    $originalUrl = 'https://original-domain.com'.$webhookPath;

    $params = ['MessageSid' => 'SM123456'];

    // Generate valid signature for the original URL
    $validSignature = WebhookSignatureHelper::generateValidSignature($originalUrl, $params, 'test_auth_token');

    // Create a request that pretends to be from a different URL but with the same signature
    $request = Mockery::mock('Illuminate\Http\Request');
    $request->shouldReceive('isMethod')->with('post')->andReturn(true);
    $request->shouldReceive('post')->andReturn($params);
    $request->shouldReceive('header')->with('X-Twilio-Signature')->andReturn($validSignature);

    // This is the key part - fullUrl() returns a different URL than what was used to generate the signature
    $request->shouldReceive('fullUrl')->andReturn('https://manipulated-domain.com'.$webhookPath);

    // Create the middleware
    $middleware = new VerifyTwilioWebhook;

    // The next handler that should not be called
    $next = function ($req) {
        return 'This should not be returned';
    };

    // The middleware should abort with 403
    try {
        $middleware->handle($request, $next);
        $this->fail('Expected abort was not called');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('escapes output in webhook responses', function () {
    // Check that responses don't include unescaped data that could lead to XSS
    config(['twilio-laravel.validate_webhook' => false]);

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');

    // Send webhook with potentially malicious payload
    $response = $this->postJson($webhookPath, [
        'MessageSid' => '<script>alert("XSS")</script>',
        'From' => '+12345678901',
    ]);

    // Response should be JSON (not HTML with unescaped script tags)
    $response->assertHeader('Content-Type', 'application/json');

    // Check that JSON properly escapes the values
    $responseData = $response->json();
    expect($responseData)->toBeArray();

    // The payload isn't echoed back in this implementation, but this test
    // would be important if the response included any request data
});

it('handles server errors gracefully', function () {
    // Test that errors are handled securely without exposing system details
    config(['twilio-laravel.validate_webhook' => true]);
    config(['twilio-laravel.auth_token' => null]); // should cause a 500 error

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');

    $response = $this->withHeaders([
        'X-Twilio-Signature' => 'some-signature',
    ])->postJson($webhookPath, [
        'MessageSid' => 'SM123456',
    ]);

    // Should return a proper error without leaking implementation details
    $response->assertStatus(500);

    // Should not expose sensitive information
    $response->assertDontSee('stack trace');
    $response->assertDontSee('database');
    $response->assertDontSee('password');
});

it('has route properly defined with middleware', function () {
    // Verify the webhook route is correctly defined with security middleware
    $webhookPath = config('twilio-laravel.webhook_path');

    // Remove leading slash if present as Laravel routes are stored without it
    $webhookPath = ltrim($webhookPath, '/');

    $routes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'middlewares' => $route->gatherMiddleware(),
        ];
    });

    // Find our webhook route
    $webhookRoute = $routes->first(function ($route) use ($webhookPath) {
        return $route['uri'] === $webhookPath && in_array('POST', $route['methods']);
    });

    expect($webhookRoute)->not->toBeNull();

    // Check it has the verification middleware
    $hasVerifyMiddleware = collect($webhookRoute['middlewares'])->contains(function ($middleware) {
        return str_contains($middleware, 'VerifyTwilioWebhook');
    });

    expect($hasVerifyMiddleware)->toBeTrue();
});
