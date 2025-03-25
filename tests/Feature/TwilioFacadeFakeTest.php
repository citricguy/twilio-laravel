<?php

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Testing\TwilioServiceFake;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;

uses(\Orchestra\Testbench\TestCase::class)->in('Feature');

beforeEach(function () {
    Twilio::fake();
});

test('it returns a fake instance', function () {
    expect(app('twilio-sms'))->toBeInstanceOf(TwilioServiceFake::class);
});

test('it records sent messages', function () {
    // Arrange
    Event::fake([TwilioMessageSent::class]);

    // Act
    Twilio::sendMessageNow('+1234567890', 'Test message');
    
    // Assert
    Twilio::assertSent(function ($message) {
        return $message->to === '+1234567890' && 
               $message->body === 'Test message' &&
               $message->status === 'sent';
    });
    
    Event::assertDispatched(TwilioMessageSent::class, function ($event) {
        return $event->to === '+1234567890' && 
               $event->message === 'Test message';
    });
});

test('it records queued messages', function () {
    // Arrange
    Event::fake([TwilioMessageQueued::class]);

    // Act
    Twilio::sendMessage('+1234567890', 'Test message');
    
    // Assert
    Twilio::assertSent(function ($message) {
        return $message->to === '+1234567890' && 
               $message->body === 'Test message' &&
               $message->type === 'queued';
    });
    
    Event::assertDispatched(TwilioMessageQueued::class);
});

test('it can assert sent to recipient', function () {
    // Act
    Twilio::sendMessage('+1234567890', 'Test message');
    
    // Assert
    Twilio::assertSentTo('+1234567890');
});

test('it can assert sent count', function () {
    // Act
    Twilio::sendMessage('+1234567890', 'First message');
    Twilio::sendMessage('+1987654321', 'Second message');
    
    // Assert
    Twilio::assertSentCount(2);
});

test('it can assert nothing sent', function () {
    // No messages sent
    Twilio::assertNothingSent();
    
    // If we get here, the assertion passed
    expect(true)->toBeTrue();
});

test('it fails assertion when expected', function () {
    // Act - no messages sent
    
    // Assert - this should fail because no messages were sent
    expect(fn() => Twilio::assertSent())->toThrow(AssertionFailedError::class);
});

test('it fails sent count when expected', function () {
    // Act
    Twilio::sendMessage('+1234567890', 'Test message');
    
    // Assert - this should fail because only 1 message was sent
    expect(fn() => Twilio::assertSentCount(2))->toThrow(AssertionFailedError::class);
});

test('it properly handles options', function () {
    // Act
    Twilio::sendMessage(
        '+1234567890',
        'Test message',
        ['from' => '+1987654321', 'mediaUrls' => ['https://example.com/image.jpg']]
    );
    
    // Assert
    Twilio::assertSent(function ($message) {
        return $message->to === '+1234567890' && 
               $message->options['from'] === '+1987654321' &&
               $message->options['mediaUrls'][0] === 'https://example.com/image.jpg';
    });
});
