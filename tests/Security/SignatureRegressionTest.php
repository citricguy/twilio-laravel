<?php

use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twilio\Security\RequestValidator;

beforeEach(function () {
    config(['twilio-laravel.auth_token' => 'test-token', 'twilio-laravel.validate_webhook' => true]);
});

it('validates original form bytes despite transformed input and query encoding', function ($tampered) {
    $url = 'https://example.test/webhooks/twilio?z=last&a=first%20value&x=%2f';
    $original = ['Body' => '  message  ', 'Empty' => '', 'From' => '+15550000002'];
    $signature = (new RequestValidator('test-token'))->computeSignature($url, $original);
    $body = http_build_query($original);
    $request = Request::create($url, 'POST', ['Body' => 'message', 'Empty' => null, 'From' => '+15550000002'], server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_X_TWILIO_SIGNATURE' => $signature], content: $body.($tampered ? '&Extra=changed' : ''));
    try {
        $response = (new VerifyTwilioWebhook)->handle($request, fn () => new Response('accepted'));
        expect($tampered)->toBeFalse()->and($response->getContent())->toBe('accepted');
    } catch (HttpException $e) {
        expect($tampered)->toBeTrue()->and($e->getStatusCode())->toBe(403);
    }
})->with([false, true]);

it('validates raw JSON hashes and rejects tampered or malformed hashes', function ($variant) {
    $body = '{"Body":"  sensitive  ","MessageSid":"SM_TEST"}';
    $hash = hash('sha256', $body);
    $query = match ($variant) {
        'malformed' => 'bodySHA256=bad', 'array' => 'bodySHA256[]=bad', default => 'bodySHA256='.$hash
    };
    $url = 'https://example.test/webhooks/twilio?'.$query;
    $signature = (new RequestValidator('test-token'))->computeSignature($url);
    $request = Request::create($url, 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWILIO_SIGNATURE' => $signature], content: $variant === 'tampered' ? $body.' ' : $body);
    try {
        $result = (new VerifyTwilioWebhook)->handle($request, fn () => new Response('accepted'));
        expect($variant)->toBe('valid')->and($result->getStatusCode())->toBe(200);
    } catch (HttpException $e) {
        expect($variant)->not->toBe('valid')->and($e->getStatusCode())->toBe(403);
    }
})->with(['valid', 'tampered', 'malformed', 'array']);

it('honors trusted proxy policy when reconstructing the external URL', function ($trusted) {
    Request::setTrustedProxies($trusted ? ['10.0.0.1'] : [], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX);
    try {
        $signature = (new RequestValidator('test-token'))->computeSignature('https://public.example.test/prefix/webhooks/twilio?z=2&a=1', ['Body' => 'hello']);
        $request = Request::create('http://internal.test/webhooks/twilio?z=2&a=1', 'POST', ['Body' => 'hello'], server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'public.example.test', 'HTTP_X_FORWARDED_PORT' => '443', 'HTTP_X_FORWARDED_PREFIX' => '/prefix', 'HTTP_X_TWILIO_SIGNATURE' => $signature]);
        try {
            $result = (new VerifyTwilioWebhook)->handle($request, fn () => new Response);
            expect($trusted)->toBeTrue()->and($result->getStatusCode())->toBe(200);
        } catch (HttpException $e) {
            expect($trusted)->toBeFalse()->and($e->getStatusCode())->toBe(403);
        }
    } finally {
        Request::setTrustedProxies([], -1);
    }
})->with([true, false]);

it('rejects nested form values without leaking them or raising SDK warnings', function () {
    $request = Request::create('https://example.test/webhooks/twilio', 'POST', ['private' => [['SECRET']]], server: ['HTTP_X_TWILIO_SIGNATURE' => 'invalid']);
    expect(fn () => (new VerifyTwilioWebhook)->handle($request, fn () => throw new LogicException('must not dispatch')))->toThrow(HttpException::class, 'Invalid Twilio webhook signature.');
});

it('supports signed GET requests when middleware is used on a custom route', function () {
    $url = 'https://example.test/custom?z=2&a=1';
    $request = Request::create($url, 'GET', server: ['HTTP_X_TWILIO_SIGNATURE' => (new RequestValidator('test-token'))->computeSignature($url)]);
    expect((new VerifyTwilioWebhook)->handle($request, fn () => 'ok'))->toBe('ok');
});

it('retains legacy signed JSON parameter verification', function () {
    $payload = ['MessageSid' => 'SM_TEST', 'Body' => 'hello'];
    $url = 'http://localhost/'.ltrim(config('twilio-laravel.webhook_path'), '/');
    $signature = (new RequestValidator('test-token'))->computeSignature($url, $payload);
    $this->withHeader('X-Twilio-Signature', $signature)->postJson($url, $payload)->assertStatus(202);
});
