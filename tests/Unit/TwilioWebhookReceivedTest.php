<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

/**
 * Test TwilioWebhookReceived event class
 */
it('sets payload in constructor', function () {
    $payload = ['MessageSid' => 'SM123'];
    $event = new TwilioWebhookReceived($payload);

    expect($event->payload)->toBe($payload);
});

// Original type detection tests
it('auto-determines message type', function () {
    $payload = ['MessageSid' => 'SM123'];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_MESSAGE_GENERIC);
});

it('auto-determines voice inbound type', function () {
    $payload = ['CallSid' => 'CA123'];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_INBOUND);
});

it('differentiates between voice inbound and status based on status value', function () {
    // In-progress should be identified as inbound
    $inboundPayload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'in-progress',
    ];
    $inboundEvent = new TwilioWebhookReceived($inboundPayload);
    expect($inboundEvent->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_INBOUND);

    // Ringing should be identified as inbound
    $ringingPayload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'ringing',
    ];
    $ringingEvent = new TwilioWebhookReceived($ringingPayload);
    expect($ringingEvent->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_INBOUND);

    // Completed should be identified as status
    $statusPayload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'completed',
    ];
    $statusEvent = new TwilioWebhookReceived($statusPayload);
    expect($statusEvent->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_STATUS);
});

it('allows custom type to be specified', function () {
    $payload = ['MessageSid' => 'SM123'];
    $event = new TwilioWebhookReceived($payload, 'custom-type');

    expect($event->type)->toBe('custom-type');
});

it('sets null type for unknown payloads', function () {
    $payload = ['UnknownField' => 'value'];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBeNull();
});

// New tests for enhanced type detection
it('detects voice status updates', function () {
    $payload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'completed',
    ];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_STATUS)
        ->and($event->isVoiceStatusUpdate())->toBeTrue()
        ->and($event->isStatusUpdate())->toBeTrue()
        ->and($event->getStatusType())->toBe('completed');
});

it('detects message status updates', function () {
    $payload = [
        'MessageSid' => 'SM123',
        'MessageStatus' => 'delivered',
    ];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_MESSAGE_STATUS_PREFIX.'delivered')
        ->and($event->isMessageStatusUpdate())->toBeTrue()
        ->and($event->isStatusUpdate())->toBeTrue()
        ->and($event->getStatusType())->toBe('delivered');
});

it('detects inbound SMS messages', function () {
    $payload = [
        'MessageSid' => 'SM123',
        'Body' => 'Test message',
        'NumMedia' => '0',
    ];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_MESSAGE_INBOUND_SMS)
        ->and($event->isInboundMessage())->toBeTrue()
        ->and($event->isInboundSms())->toBeTrue()
        ->and($event->isInboundMms())->toBeFalse();
});

it('detects inbound MMS messages', function () {
    $payload = [
        'MessageSid' => 'SM123',
        'Body' => 'Test message with media',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://example.com/image.jpg',
    ];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_MESSAGE_INBOUND_MMS)
        ->and($event->isInboundMessage())->toBeTrue()
        ->and($event->isInboundMms())->toBeTrue()
        ->and($event->isInboundSms())->toBeFalse();
});

it('detects inbound voice calls', function () {
    $payload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'ringing',
    ];
    $event = new TwilioWebhookReceived($payload);

    expect($event->type)->toBe(TwilioWebhookReceived::TYPE_VOICE_INBOUND)
        ->and($event->isInboundVoiceCall())->toBeTrue()
        ->and($event->isVoiceStatusUpdate())->toBeFalse();
});

it('correctly identifies voice and message webhook categories', function () {
    // Voice webhook
    $voicePayload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'completed',
    ];
    $voiceEvent = new TwilioWebhookReceived($voicePayload);
    expect($voiceEvent->isVoiceWebhook())->toBeTrue()
        ->and($voiceEvent->isMessageWebhook())->toBeFalse();

    // Message webhook
    $messagePayload = [
        'MessageSid' => 'SM123',
        'Body' => 'Test message',
    ];
    $messageEvent = new TwilioWebhookReceived($messagePayload);
    expect($messageEvent->isMessageWebhook())->toBeTrue()
        ->and($messageEvent->isVoiceWebhook())->toBeFalse();
});

it('normalizes status values to lowercase', function () {
    // Mixed-case status for message
    $messagePayload = [
        'MessageSid' => 'SM123',
        'MessageStatus' => 'DeLiVeReD',
    ];
    $messageEvent = new TwilioWebhookReceived($messagePayload);
    expect($messageEvent->getStatusType())->toBe('delivered');

    // Mixed-case status for voice
    $voicePayload = [
        'CallSid' => 'CA123',
        'CallStatus' => 'ComPLeTeD',
    ];
    $voiceEvent = new TwilioWebhookReceived($voicePayload);
    expect($voiceEvent->getStatusType())->toBe('completed');
});
