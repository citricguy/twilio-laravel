<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Facades\TwilioSms;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

it('can send messages through the facade', function () {
    Queue::fake();
    Event::fake();

    // Enable queuing for this test
    config(['twilio-laravel.queue_messages' => true]);

    // Send a message via the facade
    TwilioSms::sendMessage('+12345678901', 'Test message via facade');

    // Check that the job was queued
    Queue::assertPushed(SendTwilioMessage::class, function ($job) {
        return $job->to === '+12345678901' && $job->message === 'Test message via facade';
    });

    // Verify the event was fired
    Event::assertDispatched(TwilioMessageQueued::class);
});

it('handles sending immediate messages via the facade', function () {
    Event::fake();

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
    TwilioSms::sendMessageNow('+12345678901', 'Send immediately');
});

it('handles different configuration values', function () {
    Queue::fake();
    Event::fake();

    // Configure different queue name
    config(['twilio-laravel.queue_messages' => true]);
    config(['twilio-laravel.queue_name' => 'twilio-messages']);

    // Send message
    TwilioSms::sendMessage('+12345678901', 'Custom queue test');

    // Verify it was pushed to the right queue
    Queue::assertPushed(function (SendTwilioMessage $job) {
        return $job->queue === 'twilio-messages';
    });
});

it('respects custom queue options', function () {
    Queue::fake();
    Event::fake();

    // Configure queuing
    config(['twilio-laravel.queue_messages' => true]);

    // Send message with custom queue
    TwilioSms::sendMessage('+12345678901', 'Test message', [
        'queue' => 'custom-queue-name',
    ]);

    // Verify it was pushed to the right queue
    Queue::assertPushed(function (SendTwilioMessage $job) {
        return $job->queue === 'custom-queue-name';
    });
});

it('can set custom from number per message', function () {
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
    TwilioSms::sendMessageNow('+12345678901', 'Custom from', [
        'from' => '+15551234567',
    ]);
});
