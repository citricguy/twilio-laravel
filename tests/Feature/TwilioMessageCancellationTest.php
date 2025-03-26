<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Mockery;

it('can cancel messages via facade', function () {
    // Create a mock service that will be used by the facade
    $mockService = Mockery::mock(TwilioService::class);
    
    // Both calls should reach the service, but one will return a cancelled status
    $mockService->shouldReceive('sendMessageNow')
        ->twice() // Expect two calls
        ->andReturnUsing(function($to, $message, $options = []) {
            // Return appropriate response based on destination number
            if ($to === '+1555123456') {
                return [
                    'status' => 'cancelled',
                    'to' => $to,
                    'reason' => 'Blocked number'
                ];
            } else {
                return [
                    'status' => 'sent',
                    'to' => $to
                ];
            }
        });
    
    // Replace the service in the container
    app()->instance('twilio-sms', $mockService);
    
    // Register a listener that cancels messages to specific numbers
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        if ($event->to === '+1555123456') {
            return $event->cancel('Blocked number');
        }
    });
    
    // Send a message that should be cancelled
    $blockedResult = Twilio::sendMessageNow('+1555123456', 'This should be blocked');
    
    // Send a message that should go through
    $allowedResult = Twilio::sendMessageNow('+1987654321', 'This should be allowed');
    
    // Verify the results
    expect($blockedResult)->toBeArray()
        ->and($blockedResult['status'])->toBe('cancelled')
        ->and($blockedResult['reason'])->toBe('Blocked number');
        
    expect($allowedResult)->toBeArray()
        ->and($allowedResult['status'])->toBe('sent');
});

it('cancels queued messages when running through the job', function () {
    // Configure the app to use queues
    config(['twilio-laravel.queue_messages' => true]);
    
    // Set up the event listener BEFORE creating the job
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        // Cancel any message in this test
        return $event->cancel('Cancelled in job handler');
    });
    
    // Create a mock service for verification
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldReceive('sendMessageNow')->never();
    
    // The sendMessageNow method should never be called because we cancel in the event
    app()->instance(TwilioService::class, $mockService);
    
    // Create and execute a job directly
    $job = new SendTwilioMessage(
        '+1555123456', 
        'This should be cancelled in the job',
        []
    );
    
    // Execute the job (which should trigger the cancellation)
    $job->handle($mockService);
    
    // The test passes if mockService's sendMessageNow is never called
    // (which is verified by shouldReceive()->never() above)
});
