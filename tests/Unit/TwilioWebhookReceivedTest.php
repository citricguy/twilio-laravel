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

it('auto-determines message type', function () {
    $payload = ['MessageSid' => 'SM123'];
    $event = new TwilioWebhookReceived($payload);
    
    expect($event->type)->toBe('message');
});

it('auto-determines voice type', function () {
    $payload = ['CallSid' => 'CA123'];
    $event = new TwilioWebhookReceived($payload);
    
    expect($event->type)->toBe('voice');
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
