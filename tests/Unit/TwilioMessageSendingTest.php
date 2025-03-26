<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

it('can cancel a message before sending', function () {
    // Fake the event dispatcher to track dispatched events
    Event::fake([TwilioMessageSent::class]);

    // Create an event listener that cancels messages
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        return $event->cancel('Testing cancellation');
    });

    $service = new TwilioService;

    // Mock the Twilio client to ensure it's not called
    $mockClient = Mockery::mock(\Twilio\Rest\Client::class);
    // The messages->create method should never be called
    $mockClient->shouldNotReceive('messages');

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Try to send a message
    $result = $service->sendMessageNow('+1234567890', 'This message should be cancelled');

    // Verify it was cancelled and has the right response format
    expect($result)->toBeArray()
        ->and($result['status'])->toBe('cancelled')
        ->and($result['to'])->toBe('+1234567890')
        ->and($result['reason'])->toBe('Testing cancellation');

    // Make sure TwilioMessageSent event was not fired
    Event::assertNothingDispatched();
});

it('can cancel a queued message before adding to queue', function () {
    Queue::fake();

    // Create an event listener that cancels messages
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        if ($event->to === '+1234567890') {
            return $event->cancel('Number is blacklisted');
        }
    });

    $service = new TwilioService;

    // Try to queue a message
    $result = $service->queueMessage('+1234567890', 'This message should be cancelled');

    // Verify it was cancelled and has the right response format
    expect($result)->toBeArray()
        ->and($result['status'])->toBe('cancelled')
        ->and($result['to'])->toBe('+1234567890')
        ->and($result['reason'])->toBe('Number is blacklisted');

    // Make sure the job was not queued
    Queue::assertNotPushed(SendTwilioMessage::class);
});

it('cancels messages in the job if cancelled at execution time', function () {
    // Mock TwilioService to verify sendMessageNow is not called if cancelled
    $mockService = Mockery::mock(TwilioService::class);
    $mockService->shouldNotReceive('sendMessageNow');

    // Create the job
    $job = new SendTwilioMessage('+1234567890', 'This should be cancelled', []);

    // Set up an event listener to cancel the message when TwilioMessageSending is fired
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        return $event->cancel('Cancelled during job execution');
    });

    // Execute the job
    $job->handle($mockService);

    // The test passes if mockService's sendMessageNow is not called
    // (which is verified by shouldNotReceive above)
});

it('allows sending when not cancelled', function () {
    // Create a mock message instance
    $mockMessage = Mockery::mock(\Twilio\Rest\Api\V2010\Account\MessageInstance::class);
    $mockMessage->sid = 'SM123456';
    $mockMessage->status = 'sent';

    // Create a mock message list
    $mockMessageList = Mockery::mock(\Twilio\Rest\Api\V2010\Account\MessageList::class);
    $mockMessageList->shouldReceive('create')
        ->once()
        ->andReturn($mockMessage);

    // Mock the Twilio client
    $mockClient = Mockery::mock(\Twilio\Rest\Client::class);
    $mockClient->messages = $mockMessageList;

    $service = new TwilioService;

    // Inject the mock client
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Configure a default from number
    config(['twilio-laravel.from' => '+19876543210']);

    // Create an event listener that does NOT cancel messages
    Event::fake([TwilioMessageSent::class]);

    // Send a message
    $result = $service->sendMessageNow('+1234567890', 'This message should send');

    // Verify that TwilioMessageSent was dispatched (meaning it wasn't cancelled)
    Event::assertDispatched(TwilioMessageSent::class);
});

it('works with multiple listeners where one cancels', function () {
    // Create two listeners - one that doesn't cancel and one that does
    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        // This listener doesn't cancel
    });

    Event::listen(TwilioMessageSending::class, function (TwilioMessageSending $event) {
        return $event->cancel('Cancelled by second listener');
    });

    $service = new TwilioService;

    // Mock the Twilio client to ensure it's not called
    $mockClient = Mockery::mock(\Twilio\Rest\Client::class);
    $mockClient->shouldNotReceive('messages');

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Try to send a message
    $result = $service->sendMessageNow('+1234567890', 'This should be cancelled');

    // Verify it was cancelled
    expect($result['status'])->toBe('cancelled')
        ->and($result['reason'])->toBe('Cancelled by second listener');
});
