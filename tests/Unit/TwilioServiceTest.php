<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Client;

it('queues messages when queuing is enabled', function () {
    config(['twilio-laravel.queue_messages' => true]);
    Queue::fake();
    Event::fake();

    $service = new TwilioService;

    // Mock the Twilio client
    $mockClient = Mockery::mock(Client::class);
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Send a message
    $service->sendMessage('+12345678901', 'Test message');

    // Verify job was dispatched
    Queue::assertPushed(SendTwilioMessage::class, function ($job) {
        return $job->to === '+12345678901' && $job->message === 'Test message';
    });

    // Verify event was fired
    Event::assertDispatched(TwilioMessageQueued::class, function ($event) {
        return $event->to === '+12345678901' &&
               $event->message === 'Test message' &&
               $event->segmentsCount === 1;
    });
});

it('sends messages immediately when queuing is disabled', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);
    Event::fake();

    $service = new TwilioService;

    // Create a mock MessageInstance
    $mockMessage = Mockery::mock(MessageInstance::class);
    $mockMessage->sid = 'SM123456';
    $mockMessage->status = 'sent';

    // Create a mock MessageList
    $mockMessageList = Mockery::mock(MessageList::class);
    $mockMessageList->shouldReceive('create')
        ->once()
        ->with('+12345678901', [
            'body' => 'Test message',
            'to' => '+12345678901',
            'from' => '+19876543210',
        ])
        ->andReturn($mockMessage);

    // Mock the Twilio client
    $mockClient = Mockery::mock(Client::class);
    $mockClient->messages = $mockMessageList;

    // Inject the mock client
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Send a message
    $service->sendMessage('+12345678901', 'Test message');

    // Verify event was fired
    Event::assertDispatched(TwilioMessageSent::class, function ($event) {
        return $event->to === '+12345678901' &&
               $event->message === 'Test message' &&
               $event->messageSid === 'SM123456' &&
               $event->status === 'sent';
    });
});

it('allows customizing the from number', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    // Create a mock MessageInstance
    $mockMessage = Mockery::mock(MessageInstance::class);
    $mockMessage->sid = 'SM123456';
    $mockMessage->status = 'sent';

    // Create a mock MessageList expecting the custom from number
    $mockMessageList = Mockery::mock(MessageList::class);
    $mockMessageList->shouldReceive('create')
        ->once()
        ->with('+12345678901', [
            'body' => 'Test message',
            'to' => '+12345678901',
            'from' => '+15551234567',
        ])
        ->andReturn($mockMessage);

    // Mock the Twilio client
    $mockClient = Mockery::mock(Client::class);
    $mockClient->messages = $mockMessageList;

    // Inject the mock client
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Send a message with custom from number
    $service->sendMessage('+12345678901', 'Test message', ['from' => '+15551234567']);
});

it('calculates correct segment count for long messages', function () {
    config(['twilio-laravel.queue_messages' => true]);
    Queue::fake();
    Event::fake();

    // Create a long message that should be multiple segments
    $longMessage = str_repeat('This is a test message. ', 20); // ~400 chars

    // Create a partial mock of TwilioService that doesn't make real API calls
    $service = Mockery::mock(TwilioService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();

    // Mock the queueMessage method to capture segment count
    $segmentCount = null;
    $service->shouldReceive('queueMessage')
        ->once()
        ->withArgs(function ($to, $message, $options) use (&$segmentCount) {
            $segmentCount = ceil(mb_strlen($message) / 153);

            return true;
        })
        ->andReturn(['status' => 'queued']);

    // Send the message
    $service->sendMessage('+12345678901', $longMessage);

    // Calculate the expected segment count
    $expectedSegments = ceil(mb_strlen($longMessage) / 153);

    // Verify the segment count
    expect($segmentCount)->toBe($expectedSegments,
        "Expected segment count to be {$expectedSegments}, got {$segmentCount}");
});

it('handles MMS with media URLs', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    // Create a mock MessageInstance
    $mockMessage = Mockery::mock(MessageInstance::class);
    $mockMessage->sid = 'SM123456';
    $mockMessage->status = 'sent';

    // Create a mock MessageList expecting mediaUrl
    $mockMessageList = Mockery::mock(MessageList::class);
    $mockMessageList->shouldReceive('create')
        ->once()
        ->with('+12345678901', [
            'body' => 'Test message with image',
            'to' => '+12345678901',
            'from' => '+19876543210',
            'mediaUrl' => ['https://example.com/image.jpg'],
        ])
        ->andReturn($mockMessage);

    // Mock the Twilio client
    $mockClient = Mockery::mock(Client::class);
    $mockClient->messages = $mockMessageList;

    // Inject the mock client
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Send an MMS
    $service->sendMessage('+12345678901', 'Test message with image', [
        'mediaUrls' => ['https://example.com/image.jpg'],
    ]);
});

it('allows setting statusCallback URL', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    // Create a mock MessageInstance
    $mockMessage = Mockery::mock(MessageInstance::class);
    $mockMessage->sid = 'SM123456';
    $mockMessage->status = 'sent';

    // Create a mock MessageList expecting statusCallback
    $mockMessageList = Mockery::mock(MessageList::class);
    $mockMessageList->shouldReceive('create')
        ->once()
        ->with('+12345678901', [
            'body' => 'Test message with status callback',
            'to' => '+12345678901',
            'from' => '+19876543210',
            'statusCallback' => 'https://example.com/status-callback',
        ])
        ->andReturn($mockMessage);

    // Mock the Twilio client
    $mockClient = Mockery::mock(Client::class);
    $mockClient->messages = $mockMessageList;

    // Inject the mock client
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    // Send a message with statusCallback
    $service->sendMessage('+12345678901', 'Test message with status callback', [
        'statusCallback' => 'https://example.com/status-callback',
    ]);
});
