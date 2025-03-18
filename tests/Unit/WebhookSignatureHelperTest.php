<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Helpers\WebhookSignatureHelper;
use Illuminate\Support\Facades\Config;

/**
 * Tests for WebhookSignatureHelper class
 */
it('generates signatures correctly', function () {
    // Use a known auth token, URL and params
    $authToken = '12345';
    $url = 'https://example.com/webhooks/twilio';
    $params = ['foo' => 'bar'];

    // Generate the signature
    $signature = WebhookSignatureHelper::generateValidSignature($url, $params, $authToken);

    // Verify it's a non-empty string
    expect($signature)->toBeString();
    expect($signature)->not->toBeEmpty();

    // Verify the signature format (Twilio signatures are base64)
    expect(base64_decode($signature, true))->not->toBeFalse();
});

it('validates signatures correctly', function () {
    // Use a known auth token, URL and params
    $authToken = '12345';
    $url = 'https://example.com/webhooks/twilio';
    $params = ['foo' => 'bar'];

    // Generate a signature with our helper
    $signature = WebhookSignatureHelper::generateValidSignature($url, $params, $authToken);

    // Verify it validates
    $isValid = WebhookSignatureHelper::isValidSignature($signature, $url, $params, $authToken);
    expect($isValid)->toBeTrue();

    // Verify invalid signatures fail
    $invalidSig = 'invalid_signature';
    $isInvalid = WebhookSignatureHelper::isValidSignature($invalidSig, $url, $params, $authToken);
    expect($isInvalid)->toBeFalse();
});

it('uses config auth token when not explicitly provided', function () {
    // Set config value
    Config::set('twilio-laravel.auth_token', '12345');

    $url = 'https://example.com/webhooks/twilio';
    $params = ['foo' => 'bar'];

    // Generate signature without passing auth token
    $signature = WebhookSignatureHelper::generateValidSignature($url, $params);

    // It should use the config value and create a valid signature
    $isValid = WebhookSignatureHelper::isValidSignature($signature, $url, $params);
    expect($isValid)->toBeTrue();
});

it('invalidates when params change', function () {
    $authToken = '12345';
    $url = 'https://example.com/webhooks/twilio';
    $params = ['foo' => 'bar'];

    // Generate signature with original params
    $signature = WebhookSignatureHelper::generateValidSignature($url, $params, $authToken);

    // Change params and verify signature is no longer valid
    $changedParams = ['foo' => 'changed'];
    $isValid = WebhookSignatureHelper::isValidSignature($signature, $url, $changedParams, $authToken);
    expect($isValid)->toBeFalse();
});

it('invalidates when URL changes', function () {
    $authToken = '12345';
    $url = 'https://example.com/webhooks/twilio';
    $params = ['foo' => 'bar'];

    // Generate signature with original URL
    $signature = WebhookSignatureHelper::generateValidSignature($url, $params, $authToken);

    // Change URL and verify signature is no longer valid
    $changedUrl = 'https://example.com/different/path';
    $isValid = WebhookSignatureHelper::isValidSignature($signature, $changedUrl, $params, $authToken);
    expect($isValid)->toBeFalse();
});
