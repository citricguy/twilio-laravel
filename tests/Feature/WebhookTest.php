<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Mockery;
use Twilio\Security\RequestValidator;

it('receives webhooks when validation is disabled', function () {
    // Disable validation for this test
    config(['twilio-laravel.validate_webhook' => false]);
    
    // Mock event dispatcher
    Event::fake();
    
    // Send a test webhook
    $response = $this->postJson('/webhooks/twilio', [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'To' => '+19876543210',
        'Body' => 'Test message'
    ]);
    
    // Check response
    $response->assertStatus(202);
    
    // Verify event was dispatched
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return isset($event->payload['MessageSid']) 
            && $event->payload['MessageSid'] === 'SM123456'
            && $event->type === 'message';
    });
});
