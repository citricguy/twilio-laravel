<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * Tests for the VerifyWebhookSetupCommand
 */
it('shows warning when auth token is not configured', function () {
    Config::set('twilio-laravel.auth_token', 'your-twilio-auth-token');

    $result = Artisan::call('twilio:verify-webhook-setup');
    $output = Artisan::output();

    expect($result)->toBe(0);
    expect($output)->toContain('Auth token is not configured properly');
    expect($output)->toContain('Set TWILIO_TOKEN in your .env file');
});

it('shows success when auth token is configured', function () {
    Config::set('twilio-laravel.auth_token', 'a_real_configured_token');

    $result = Artisan::call('twilio:verify-webhook-setup');
    $output = Artisan::output();

    expect($result)->toBe(0);
    expect($output)->toContain('Auth token is configured');
});

it('shows warning when webhook validation is disabled', function () {
    Config::set('twilio-laravel.auth_token', 'configured_token');
    Config::set('twilio-laravel.validate_webhook', false);

    $result = Artisan::call('twilio:verify-webhook-setup');
    $output = Artisan::output();

    expect($result)->toBe(0);
    expect($output)->toContain('Webhook signature validation is DISABLED');
});

it('shows correct webhook url with custom path', function () {
    Config::set('twilio-laravel.webhook_path', 'custom/twilio/endpoint');

    $result = Artisan::call('twilio:verify-webhook-setup', ['--url' => 'https://example.org']);
    $output = Artisan::output();

    expect($result)->toBe(0);
    expect($output)->toContain('https://example.org/custom/twilio/endpoint');
});
