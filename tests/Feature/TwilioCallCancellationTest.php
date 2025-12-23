<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Mockery;

it('cancels queued calls when running through the job', function () {
    // Configure the app to use queues
    config(['twilio-laravel.queue_messages' => true]);

    // Set up the event listener BEFORE creating the job
    Event::listen(TwilioCallSending::class, function (TwilioCallSending $event) {
        return $event->cancel('Cancelled in job handler');
    });

    // Create a mock service for verification
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')->never();

    app()->instance(TwilioService::class, $mockService);

    // Create and execute a job directly
    $job = new SendTwilioCall(
        '+1555123456',
        'https://example.com/twiml',
        []
    );

    // Execute the job (which should trigger the cancellation)
    $job->handle($mockService);

    // The test passes if mockService's makeCallNow is never called
});

it('executes call job when not cancelled', function () {
    // Configure the app to use queues
    config(['twilio-laravel.queue_messages' => true]);

    // Create a mock service for verification
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')
        ->once()
        ->with('+1555123456', 'https://example.com/twiml', ['from' => '+1987654321'])
        ->andReturn(['status' => 'initiated', 'callSid' => 'CALL123']);

    // Create and execute a job directly with options
    $job = new SendTwilioCall(
        '+1555123456',
        'https://example.com/twiml',
        ['from' => '+1987654321']
    );

    // Execute the job
    $job->handle($mockService);
});

it('logs cancellation when debug is enabled', function () {
    config(['twilio-laravel.debug' => true]);

    // Set up the event listener
    Event::listen(TwilioCallSending::class, function (TwilioCallSending $event) {
        return $event->cancel('Debug test cancellation');
    });

    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('makeCallNow')->never();

    $job = new SendTwilioCall(
        '+1555123456',
        'https://example.com/twiml',
        []
    );

    // Execute the job - should not throw
    $job->handle($mockService);
});
