<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Facades\Twilio;

beforeEach(function () {
    // Always start with a fresh fake
    Twilio::fake();
});

it('demonstrates how to assert a call was made', function () {
    // Make a call with the Twilio facade
    Twilio::makeCallNow('+15551234567', 'https://example.com/twiml');

    // Basic assertion that a call was made
    Twilio::assertCallMade();

    // Assert a call was made to a specific number
    Twilio::assertCalledTo('+15551234567');

    // Assert call count
    Twilio::assertCallCount(1);

    // Advanced assertion with callback to check call details
    Twilio::assertCallMade(function ($call) {
        return $call->to === '+15551234567' &&
               $call->url === 'https://example.com/twiml';
    });
});

it('demonstrates options assertions for calls', function () {
    // Make a call with options
    Twilio::makeCallNow(
        '+15551234567',
        'https://example.com/twiml',
        [
            'from' => '+16665551234',
            'statusCallback' => 'https://example.com/status',
        ]
    );

    // Assert the call had the correct options
    Twilio::assertCallMade(function ($call) {
        return $call->to === '+15551234567' &&
               $call->options['from'] === '+16665551234' &&
               $call->options['statusCallback'] === 'https://example.com/status';
    });
});

it('demonstrates asserting no calls were made', function () {
    // Don't make any calls

    // Assert no calls were made
    Twilio::assertNoCalls();

    // This would fail if any calls were made:
    // Twilio::assertCallMade();
});

it('demonstrates testing queued calls', function () {
    // Queue a call instead of sending immediately
    Twilio::makeCall('+15551234567', 'https://example.com/twiml');

    // Assert the call was queued
    Twilio::assertCallMade(function ($call) {
        return $call->type === 'queued';
    });
});
