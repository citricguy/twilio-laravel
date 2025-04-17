<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Helpers\WebhookSignatureHelper;
use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Mockery;
use Symfony\Component\HttpFoundation\HeaderBag;

it('passes validation with valid signature', function () {
    // Set test auth token
    $authToken = 'test_auth_token_12345';
    Config::set('twilio-laravel.auth_token', $authToken);
    Config::set('twilio-laravel.validate_webhook', true);

    // Create a mock request with params
    $url = 'https://example.com/webhooks/twilio';
    $params = ['MessageSid' => 'SM123456', 'Body' => 'Test message'];

    // Generate an actual valid signature using Twilio's algorithm
    $validSignature = WebhookSignatureHelper::generateValidSignature($url, $params, $authToken);

    // Create a request with this signature
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('fullUrl')->andReturn($url);
    $request->shouldReceive('isMethod')->with('post')->andReturn(true);
    $request->shouldReceive('post')->andReturn($params);
    $request->shouldReceive('header')->with('X-Twilio-Signature')->andReturn($validSignature);
    $request->shouldReceive('getContent')->andReturn('');
    $request->headers = new HeaderBag([]);

    // Middleware should pass this request through
    $middleware = new VerifyTwilioWebhook;

    $next = function ($req) {
        return new Response('Passed middleware');
    };

    $response = $middleware->handle($request, $next);
    expect($response->getContent())->toBe('Passed middleware');
});

it('rejects invalid signature', function () {
    // Set test auth token
    Config::set('twilio-laravel.auth_token', 'test_auth_token_12345');
    Config::set('twilio-laravel.validate_webhook', true);

    // Create a mock request with params and invalid signature
    $url = 'https://example.com/webhooks/twilio';
    $params = ['MessageSid' => 'SM123456'];
    $invalidSignature = 'invalid_signature_value';

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('fullUrl')->andReturn($url);
    $request->shouldReceive('isMethod')->with('post')->andReturn(true);
    $request->shouldReceive('post')->andReturn($params);
    $request->shouldReceive('header')->with('X-Twilio-Signature')->andReturn($invalidSignature);
    $request->shouldReceive('getContent')->andReturn('');
    $request->shouldReceive('method')->andReturn('POST');
    $request->shouldReceive('query')->andReturn([]);
    $request->headers = new HeaderBag([]);

    // Middleware should abort with 403
    $middleware = new VerifyTwilioWebhook;

    $next = function ($req) {
        return new Response('Should not reach here');
    };

    try {
        $middleware->handle($request, $next);
        $this->fail('Expected abort was not called');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});
