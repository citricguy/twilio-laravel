<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

/**
 * Tests for different webhook payload types and formats
 */
it('correctly identifies SMS webhook type', function () {
    config(['twilio-laravel.validate_webhook' => false]);
    Event::fake();
    
    $response = $this->postJson('/webhooks/twilio', [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'Body' => 'Test message'
    ]);
    
    $response->assertStatus(202);
    
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->type === 'message';
    });
});

it('correctly identifies voice webhook type', function () {
    config(['twilio-laravel.validate_webhook' => false]);
    Event::fake();
    
    $response = $this->postJson('/webhooks/twilio', [
        'CallSid' => 'CA123456',
        'From' => '+12345678901',
        'CallStatus' => 'in-progress'
    ]);
    
    $response->assertStatus(202);
    
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->type === 'voice';
    });
});

it('handles empty payloads gracefully', function () {
    config(['twilio-laravel.validate_webhook' => false]);
    Event::fake();
    
    $response = $this->postJson('/webhooks/twilio', []);
    
    $response->assertStatus(202);
    
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->type === null && empty($event->payload);
    });
});

it('processes webhooks with complex nested payloads', function () {
    config(['twilio-laravel.validate_webhook' => false]);
    Event::fake();
    
    $response = $this->postJson('/webhooks/twilio', [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'To' => '+19876543210',
        'Body' => 'Test message',
        'NumMedia' => '1',
        'MediaContentType0' => 'image/jpeg',
        'MediaUrl0' => 'https://example.com/image.jpg',
        'extra' => [
            'nested' => 'value',
            'items' => [1, 2, 3]
        ]
    ]);
    
    $response->assertStatus(202);
    
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->payload['NumMedia'] === '1' && 
               isset($event->payload['extra']['nested']);
    });
});
