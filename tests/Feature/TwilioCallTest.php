<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

beforeEach(function () {
    // Set test credentials to prevent client creation error
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);
});

it('can make voice calls through the facade', function () {
    Queue::fake();
    Event::fake();

    // Enable queuing for this test
    config(['twilio-laravel.queue_messages' => true]);

    // Make a call via the facade
    Twilio::makeCall('+12345678901', 'https://example.com/twiml', []);

    // Check that the job was queued
    Queue::assertPushed(SendTwilioCall::class, function ($job) {
        return $job->to === '+12345678901' &&
               $job->url === 'https://example.com/twiml';
    });

    // Verify the event was fired
    Event::assertDispatched(TwilioCallQueued::class);
});

it('can make immediate voice calls via the facade', function () {
    Event::fake();

    // Mock the TwilioService to avoid actual API calls
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')
        ->once()
        ->withArgs(function ($to, $url, $options = []) {
            return $to === '+12345678901' &&
                   $url === 'https://example.com/twiml/immediate';
        })
        ->andReturn(['status' => 'initiated']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Use the facade to make a call
    Twilio::makeCallNow('+12345678901', 'https://example.com/twiml/immediate');
});

it('respects custom queue options for calls', function () {
    Queue::fake();
    Event::fake();

    // Configure queuing
    config(['twilio-laravel.queue_messages' => true]);

    // Make a call with custom queue
    Twilio::makeCall('+12345678901', 'https://example.com/twiml', [
        'queue' => 'custom-call-queue',
    ]);

    // Verify it was pushed to the right queue
    Queue::assertPushed(function (SendTwilioCall $job) {
        return $job->queue === 'custom-call-queue';
    });
});

it('can set custom from number for calls', function () {
    // Mock the TwilioService to verify custom from
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')
        ->once()
        ->withArgs(function ($to, $url, $options) {
            return $to === '+12345678901' &&
                   $url === 'https://example.com/twiml' &&
                   $options['from'] === '+15551234567';
        })
        ->andReturn(['status' => 'initiated']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Make call with custom from
    Twilio::makeCallNow('+12345678901', 'https://example.com/twiml', [
        'from' => '+15551234567',
    ]);
});

it('can set status callback URL for calls', function () {
    // Mock the TwilioService to verify StatusCallback
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')
        ->once()
        ->withArgs(function ($to, $url, $options) {
            return $to === '+12345678901' &&
                   $url === 'https://example.com/twiml' &&
                   $options['statusCallback'] === 'https://example.com/callbacks/call-status';
        })
        ->andReturn(['status' => 'initiated']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Make call with StatusCallback
    Twilio::makeCallNow('+12345678901', 'https://example.com/twiml', [
        'statusCallback' => 'https://example.com/callbacks/call-status',
    ]);
});
