<?php

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Events\TwilioCallSent;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Testing\TwilioServiceFake;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function () {
    Twilio::fake();
});

test('it records calls correctly', function () {
    // Arrange
    Event::fake([TwilioCallSent::class]);

    // Act - make a call
    Twilio::makeCallNow('+1234567890', 'https://example.com/twiml');

    // Assert
    Twilio::assertCallMade(function ($call) {
        return $call->to === '+1234567890' &&
               $call->url === 'https://example.com/twiml' &&
               $call->type === 'initiated';
    });

    Event::assertDispatched(TwilioCallSent::class, function ($event) {
        return $event->to === '+1234567890' &&
               $event->url === 'https://example.com/twiml';
    });
});

test('it records queued calls', function () {
    // Arrange
    Event::fake([TwilioCallQueued::class]);

    // Act - queue a call
    Twilio::makeCall('+1234567890', 'https://example.com/twiml');

    // Assert
    Twilio::assertCallMade(function ($call) {
        return $call->to === '+1234567890' &&
               $call->url === 'https://example.com/twiml' &&
               $call->type === 'queued';
    });

    Event::assertDispatched(TwilioCallQueued::class);
});

test('it can assert called to recipient', function () {
    // Act
    Twilio::makeCall('+1234567890', 'https://example.com/twiml');

    // Assert
    Twilio::assertCalledTo('+1234567890');
});

test('it can assert call count', function () {
    // Act
    Twilio::makeCall('+1234567890', 'https://example.com/twiml1');
    Twilio::makeCall('+1987654321', 'https://example.com/twiml2');

    // Assert
    Twilio::assertCallCount(2);
});

test('it can assert no calls made', function () {
    // No calls made
    Twilio::assertNoCalls();

    // If we get here, the assertion passed
    expect(true)->toBeTrue();
});

test('it fails assertion when expected for calls', function () {
    // Act - no calls made

    // Assert - this should fail because no calls were made
    expect(fn () => Twilio::assertCallMade())->toThrow(AssertionFailedError::class);
});

test('it fails call count assertion when expected', function () {
    // Act
    Twilio::makeCall('+1234567890', 'https://example.com/twiml');

    // Assert - this should fail because only 1 call was made
    expect(fn () => Twilio::assertCallCount(2))->toThrow(AssertionFailedError::class);
});

test('it properly handles call options', function () {
    // Act
    Twilio::makeCall(
        '+1234567890',
        'https://example.com/twiml',
        ['from' => '+1987654321', 'statusCallback' => 'https://example.com/status']
    );

    // Assert
    Twilio::assertCallMade(function ($call) {
        return $call->to === '+1234567890' &&
               $call->options['from'] === '+1987654321' &&
               $call->options['statusCallback'] === 'https://example.com/status';
    });
});