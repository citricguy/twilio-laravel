<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Support\Facades\Event;

it('receives webhooks when validation is disabled', function () {
    // Disable validation for this test
    config(['twilio-laravel.validate_webhook' => false]);

    // Mock event dispatcher
    Event::fake();

    // Get webhook path from config
    $webhookPath = config('twilio-laravel.webhook_path');

    // Send a test webhook
    $response = $this->postJson($webhookPath, [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'To' => '+19876543210',
        'Body' => 'Test message',
    ]);

    // Check response
    $response->assertStatus(202);

    // Verify event was dispatched
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return isset($event->payload['MessageSid'])
            && $event->payload['MessageSid'] === 'SM123456'
            && $event->type === TwilioWebhookReceived::TYPE_MESSAGE_INBOUND_SMS;
    });
});
