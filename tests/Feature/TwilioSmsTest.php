<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

it('can send messages through the facade', function () {
    Queue::fake();
    Event::fake();

    // Set credentials to prevent client creation error
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);

    // Enable queuing for this test
    config(['twilio-laravel.queue_messages' => true]);

    // Send a message via the facade
    Twilio::sendMessage('+12345678901', 'Test message via facade');

    // Check that the job was queued
    Queue::assertPushed(SendTwilioMessage::class, function ($job) {
        return $job->to === '+12345678901' && $job->message === 'Test message via facade';
    });

    // Verify the event was fired
    Event::assertDispatched(TwilioMessageQueued::class);
});

it('handles sending immediate messages via the facade', function () {
    Event::fake();
    // Don't use Twilio::fake() here since we're using a mock

    // Mock the TwilioService to avoid actual API calls
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('sendMessageNow')
        ->once()
        ->withArgs(function ($to, $message, $options = []) {
            return $to === '+12345678901' && $message === 'Send immediately';
        })
        ->andReturn(['status' => 'sent']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Use the facade to send
    Twilio::sendMessageNow('+12345678901', 'Send immediately');
});

it('handles different configuration values', function () {
    Queue::fake();
    Event::fake();

    // Set credentials to prevent client creation error
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);

    // Configure different queue name
    config(['twilio-laravel.queue_messages' => true]);
    config(['twilio-laravel.queue_name' => 'twilio-messages']);

    // Send message
    Twilio::sendMessage('+12345678901', 'Custom queue test');

    // Verify it was pushed to the right queue
    Queue::assertPushed(function (SendTwilioMessage $job) {
        return $job->queue === 'twilio-messages';
    });
});

it('respects custom queue options', function () {
    Queue::fake();
    Event::fake();

    // Set credentials to prevent client creation error
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);

    // Configure queuing
    config(['twilio-laravel.queue_messages' => true]);

    // Send message with custom queue
    Twilio::sendMessage('+12345678901', 'Test message', [
        'queue' => 'custom-queue-name',
    ]);

    // Verify it was pushed to the right queue
    Queue::assertPushed(function (SendTwilioMessage $job) {
        return $job->queue === 'custom-queue-name';
    });
});

it('can set custom from number per message', function () {
    // Don't use Twilio::fake() here since we're using a mock

    // Mock the TwilioService to verify custom from
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('sendMessageNow')
        ->once()
        ->withArgs(function ($to, $message, $options) {
            return $to === '+12345678901' &&
                   $message === 'Custom from' &&
                   $options['from'] === '+15551234567';
        })
        ->andReturn(['status' => 'sent']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Send with custom from
    Twilio::sendMessageNow('+12345678901', 'Custom from', [
        'from' => '+15551234567',
    ]);
});

it('can set StatusCallback URL for each message', function () {
    // Don't use Twilio::fake() here since we're using a mock

    // Mock the TwilioService to verify StatusCallback
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('sendMessageNow')
        ->once()
        ->withArgs(function ($to, $message, $options) {
            return $to === '+12345678901' &&
                   $message === 'With status callback' &&
                   $options['StatusCallback'] === 'https://example.com/callbacks/status';
        })
        ->andReturn(['status' => 'sent']);

    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);

    // Send with StatusCallback
    Twilio::sendMessageNow('+12345678901', 'With status callback', [
        'StatusCallback' => 'https://example.com/callbacks/status',
    ]);
});
