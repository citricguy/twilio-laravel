# Twilio Laravel

<p align="center">
  <a href="https://packagist.org/packages/citricguy/twilio-laravel" target="_blank">
    <img src="https://img.shields.io/packagist/v/citricguy/twilio-laravel.svg?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/citricguy/twilio-laravel" target="_blank">
    <img src="https://img.shields.io/packagist/dt/citricguy/twilio-laravel.svg?style=flat-square" alt="Total Downloads">
  </a>
</p>

A Laravel package to integrate Twilio for SMS/MMS messaging, notifications, and webhooks. This package leverages the official Twilio PHP SDK and adheres to Laravel conventions, providing a seamless, queued, and event-driven solution for sending messages and processing incoming Twilio callbacks.

## 📋 Table of Contents

- [Twilio Laravel](#twilio-laravel)
  - [📋 Table of Contents](#-table-of-contents)
  - [Installation](#installation)
  - [Configuration](#configuration)
    - [Debug Mode](#debug-mode)
  - [Usage](#usage)
    - [Sending SMS Messages](#sending-sms-messages)
      - [Message Queuing](#message-queuing)
  - [Working with Webhooks](#working-with-webhooks)
    - [Webhook Types](#webhook-types)
    - [Webhook Helper Methods](#webhook-helper-methods)
    - [Handling Different Webhook Types](#handling-different-webhook-types)
    - [How Responses Work](#how-responses-work)
  - [Integration Guide](#integration-guide)
    - [Setting Up Event Listeners](#setting-up-event-listeners)
    - [Handling Immediate Responses](#handling-immediate-responses)
    - [Background Processing](#background-processing)
      - [Option 1: Queue the whole listener](#option-1-queue-the-whole-listener)
      - [Option 2: Queue specific processing tasks](#option-2-queue-specific-processing-tasks)
    - [Integration Patterns](#integration-patterns)
      - [Multiple Specialized Listeners](#multiple-specialized-listeners)
      - [Service-Based Approach](#service-based-approach)
  - [Setting Up Twilio Webhooks](#setting-up-twilio-webhooks)
    - [Step 1: Create a Webhook Endpoint](#step-1-create-a-webhook-endpoint)
    - [Step 2: Make Your Endpoint Accessible](#step-2-make-your-endpoint-accessible)
    - [Step 3: Configure Twilio to Send Webhooks](#step-3-configure-twilio-to-send-webhooks)
    - [Step 4: Test Your Webhook](#step-4-test-your-webhook)
  - [Webhook Security](#webhook-security)
    - [Webhook Validation Explained](#webhook-validation-explained)
  - [Testing](#testing)
    - [Testing Your Webhook Handlers](#testing-your-webhook-handlers)
      - [Option 1: Dispatching the Event Directly](#option-1-dispatching-the-event-directly)
      - [Option 2: Testing the HTTP Endpoint](#option-2-testing-the-http-endpoint)
      - [Option 3: Integration Testing with Response Expectations](#option-3-integration-testing-with-response-expectations)
  - [Security](#security)
  - [Credits](#credits)
  - [License](#license)

## Installation

You can install the package via composer:

```bash
composer require citricguy/twilio-laravel
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Citricguy\TwilioLaravel\TwilioLaravelServiceProvider" --tag="config"
```

This will create a `config/twilio-laravel.php` file in your app where you can modify the configuration settings.

Add the following environment variables to your `.env` file:

```
TWILIO_SID=your-twilio-account-sid
TWILIO_TOKEN=your-twilio-auth-token
TWILIO_FROM=+1234567890
TWILIO_WEBHOOK_PATH=/api/twilio/webhook
TWILIO_DEBUG=false
```

### Debug Mode

The `TWILIO_DEBUG` environment variable can be used to enable or disable debug mode. When debug mode is enabled (`TWILIO_DEBUG=true`), additional logging and debugging information will be available to help troubleshoot issues. It is recommended to keep debug mode disabled (`TWILIO_DEBUG=false`) in production environments.

## Usage

### Sending SMS Messages

You can send SMS messages using the `Twilio` facade:

```php
use Citricguy\TwilioLaravel\Facades\Twilio;

// Basic usage
Twilio::sendMessage('+1234567890', 'Hello from Twilio Laravel!');

// With custom from number
Twilio::sendMessage(
    '+1234567890',
    'Hello from a different number!',
    ['from' => '+1987654321']
);

// Send immediately (bypass queue)
Twilio::sendMessageNow('+1234567890', 'This is urgent!');

// Send MMS with media
Twilio::sendMessage(
    '+1234567890',
    'Check out this image!',
    ['mediaUrls' => ['https://example.com/image.jpg']]
);

// Custom queue options
Twilio::sendMessage(
    '+1234567890',
    'Hello from a custom queue!',
    [
        'queue' => 'high-priority',
        'delay' => 30 // delay in seconds
    ]
);
```

#### Message Queuing

By default, all messages are queued for sending. This behavior can be configured in `config/twilio-laravel.php`:

```php
// Disable queuing to send all messages immediately
'queue_messages' => false,

// Set a custom queue name
'queue_name' => 'twilio',
```

## Working with Webhooks

A key feature of this package is its ability to receive and handle webhooks from Twilio. Webhooks enable your application to respond to events like message deliveries, incoming texts, voice calls, and more.

### Webhook Types

The package automatically detects different types of Twilio webhooks and categorizes them to make handling easier:

| Type | Constant | Description | Example |
|------|----------|-------------|---------|
| Voice Inbound | `TYPE_VOICE_INBOUND` | Incoming voice call | Someone calls your Twilio number |
| Voice Status | `TYPE_VOICE_STATUS` | Voice call status update | A call ends or changes status |
| Message Inbound SMS | `TYPE_MESSAGE_INBOUND_SMS` | Incoming SMS message | Someone texts your Twilio number |
| Message Inbound MMS | `TYPE_MESSAGE_INBOUND_MMS` | Incoming MMS message | Someone sends media to your Twilio number |
| Message Status | `TYPE_MESSAGE_STATUS_PREFIX` + status | Message status update | Your sent message is delivered or fails |
| Generic Message | `TYPE_MESSAGE_GENERIC` | Other message webhook | Fallback for other message events |

### Webhook Helper Methods

The `TwilioWebhookReceived` event provides helper methods to easily identify webhook types:

```php
// High-level webhook categorization
$event->isVoiceWebhook();  // Any voice-related webhook
$event->isMessageWebhook(); // Any message-related webhook

// Inbound communication
$event->isInboundMessage(); // Any incoming message (SMS or MMS)
$event->isInboundSms();     // Specifically SMS
$event->isInboundMms();     // Specifically MMS
$event->isInboundVoiceCall(); // Incoming voice call
  
// Status updates
$event->isMessageStatusUpdate(); // Message status updates
$event->isVoiceStatusUpdate();   // Voice call status updates
$event->isStatusUpdate();        // Any status update
  
// Get the specific status value
$event->getStatusType(); // Returns normalized lowercase status value
```

### Handling Different Webhook Types

You can listen for webhook events in your `EventServiceProvider`:

```php
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

protected $listen = [
    TwilioWebhookReceived::class => [
        App\Listeners\HandleTwilioWebhook::class,
    ],
];
```

Then create a listener to handle the different webhook types:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Support\Facades\Log;

class HandleTwilioWebhook
{
    public function handle(TwilioWebhookReceived $event)
    {
        // Voice call handling - requires immediate TwiML response
        if ($event->isInboundVoiceCall()) {
            $twiml = '<?xml version="1.0" encoding="UTF-8"?>
                <Response>
                    <Say>Hello! Thanks for calling.</Say>
                </Response>';
                
            return response($twiml, 200, ['Content-Type' => 'text/xml']);
        }
        
        // Other webhook types can be handled without returning a response
        if ($event->isInboundSms()) {
            // Process the SMS...
            Log::info("SMS received: " . ($event->payload['Body'] ?? ''));
        }
        
        if ($event->isMessageStatusUpdate()) {
            // Handle status update...
            Log::info("Message status: " . $event->getStatusType());
        }
    }
}
```

### How Responses Work

The package is designed to handle both immediate responses and background processing:

1. When a webhook arrives, the controller dispatches the `TwilioWebhookReceived` event
2. Your listener processes the event and can optionally return a Response object
3. If your listener returns a Response, the controller will return it to Twilio
4. If no Response is returned, the controller sends a default 202 Accepted response

This approach allows you to:
- Return TwiML responses for voice calls (which require immediate responses)
- Simply process other webhooks without worrying about responses
- Optionally queue time-consuming processing for any webhook type

## Integration Guide

This section provides practical examples of how to integrate this package into your Laravel application.

### Setting Up Event Listeners

Register your listener in your `EventServiceProvider`:

```php
// In app/Providers/EventServiceProvider.php
protected $listen = [
    \Citricguy\TwilioLaravel\Events\TwilioWebhookReceived::class => [
        \App\Listeners\TwilioWebhookHandler::class,
    ],
];
```

### Handling Immediate Responses

For webhooks that require immediate responses (like voice calls):

```php
// app/Listeners/TwilioWebhookHandler.php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

class TwilioWebhookHandler
{
    public function handle(TwilioWebhookReceived $event)
    {
        if ($event->isInboundVoiceCall()) {
            // Voice calls require immediate TwiML responses
            return response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <Response>
                    <Say>Thank you for calling. We\'ll connect you shortly.</Say>
                    <Dial>+19876543210</Dial>
                </Response>',
                200,
                ['Content-Type' => 'text/xml']
            );
        }
        
        // For other webhooks, no return value is needed
    }
}
```

The key points:
1. DO NOT make your listener implement `ShouldQueue` if you need immediate responses
2. Return a Response object with TwiML content for voice calls
3. Set the Content-Type header to 'text/xml' for TwiML responses

### Background Processing

For webhooks that can be processed in the background:

#### Option 1: Queue the whole listener

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessMessageStatusUpdates implements ShouldQueue
{
    public function handle(TwilioWebhookReceived $event)
    {
        if ($event->isMessageStatusUpdate()) {
            // This will run in the background
            // Process the status update...
        }
    }
}
```

Register this alongside your immediate response listener:

```php
protected $listen = [
    \Citricguy\TwilioLaravel\Events\TwilioWebhookReceived::class => [
        \App\Listeners\TwilioImmediateResponseHandler::class,  // Not queued
        \App\Listeners\ProcessMessageStatusUpdates::class,     // Queued
    ],
];
```

#### Option 2: Queue specific processing tasks

```php
namespace App\Listeners;

use App\Jobs\ProcessSmsMessage;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

class TwilioWebhookHandler
{
    public function handle(TwilioWebhookReceived $event)
    {
        if ($event->isInboundVoiceCall()) {
            // Handle voice calls immediately
            return $this->generateVoiceResponse();
        }
        
        if ($event->isInboundSms()) {
            // Queue the heavy processing
            ProcessSmsMessage::dispatch($event->payload)
                ->onQueue('twilio-webhooks');
        }
    }
    
    private function generateVoiceResponse()
    {
        // Generate and return TwiML
    }
}
```

### Integration Patterns

For larger applications, consider these patterns:

#### Multiple Specialized Listeners

```php
// In EventServiceProvider.php
protected $listen = [
    \Citricguy\TwilioLaravel\Events\TwilioWebhookReceived::class => [
        \App\Listeners\TwilioVoiceHandler::class,
        \App\Listeners\TwilioMessageHandler::class,
    ],
];

// In TwilioVoiceHandler.php
public function handle(TwilioWebhookReceived $event)
{
    if (!$event->isVoiceWebhook()) {
        return; // Only process voice webhooks
    }
    
    if ($event->isInboundVoiceCall()) {
        return $this->generateTwimlResponse();
    }
}

// In TwilioMessageHandler.php (could implement ShouldQueue)
public function handle(TwilioWebhookReceived $event)
{
    if (!$event->isMessageWebhook()) {
        return; // Only process message webhooks
    }
    
    // Process message webhooks...
}
```

#### Service-Based Approach

```php
// In app/Services/TwilioWebhookService.php
namespace App\Services;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

class TwilioWebhookService
{
    public function handleVoiceCall(array $payload)
    {
        // Process voice call and return TwiML
        return response($this->generateTwiml(), 200, ['Content-Type' => 'text/xml']);
    }
    
    public function processIncomingSms(array $payload)
    {
        // Process SMS...
    }
    
    private function generateTwiml()
    {
        // Generate TwiML
    }
}

// In your listener
public function handle(TwilioWebhookReceived $event)
{
    $service = app(TwilioWebhookService::class);
    
    if ($event->isInboundVoiceCall()) {
        return $service->handleVoiceCall($event->payload);
    }
    
    if ($event->isInboundSms()) {
        $service->processIncomingSms($event->payload);
    }
}
```

## Setting Up Twilio Webhooks

### Step 1: Create a Webhook Endpoint

This package automatically creates a webhook endpoint at `/api/twilio/webhook` (or the path you've configured in your `.env` file).

### Step 2: Make Your Endpoint Accessible

During development, you can use tools like [Ngrok](https://ngrok.com/) to expose your local server to the internet:

```bash
ngrok http 8000
```

This will give you a public URL that you can use to receive webhooks.

### Step 3: Configure Twilio to Send Webhooks

1. Log into your [Twilio Console](https://www.twilio.com/console)
2. Navigate to **Phone Numbers** > **Manage** > **Active Numbers**
3. Click on the phone number you want to configure
4. Configure the webhook URLs:

   **For SMS/MMS**:
   - Scroll to the "Messaging" section
   - Under "A MESSAGE COMES IN", set the webhook URL to:
     ```
     https://your-ngrok-url.io/api/twilio/webhook
     ```
     (or your custom webhook path)
   - Set the HTTP method to **POST**

   **For Voice**:
   - Scroll to the "Voice & Fax" section
   - Under "A CALL COMES IN", set the webhook URL to:
     ```
     https://your-ngrok-url.io/api/twilio/webhook
     ```
   - Set the HTTP method to **POST**

5. Save your changes

### Step 4: Test Your Webhook

Send a text message to your Twilio number or make a call to it. You should see the webhook being received in your Laravel logs.

## Webhook Security

By default, the package validates all incoming webhook requests from Twilio using their signature validation mechanism. This ensures that webhooks are genuinely from Twilio.

You can disable this in development by setting:

```
# In .env file
TWILIO_VALIDATE_WEBHOOK=false
```

This is not recommended for production.

### Webhook Validation Explained

When Twilio sends a webhook, it includes an `X-Twilio-Signature` header that's generated based on:
- Your Twilio auth token
- The full URL of your webhook endpoint
- The request parameters

The package verifies this signature to ensure the request is legitimate.

## Testing

### Testing Your Webhook Handlers

You can test your webhook handlers in several ways:

#### Option 1: Dispatching the Event Directly

```php
// In your test
public function test_can_handle_voice_call()
{
    // Create a test payload
    $payload = [
        'CallSid' => 'CA123456',
        'From' => '+12345678901',
        'CallStatus' => 'ringing',
    ];
    
    // Create the event
    $event = new TwilioWebhookReceived($payload);
    
    // Dispatch the event and get responses
    $responses = Event::dispatch($event);
    
    // Check if a TwiML response was returned
    $this->assertInstanceOf(\Illuminate\Http\Response::class, $responses[0]);
    $this->assertStringContainsString('<Response>', $responses[0]->getContent());
}
```

#### Option 2: Testing the HTTP Endpoint

```php
public function test_webhook_endpoint_processes_sms()
{
    // Disable signature validation for testing
    config(['twilio-laravel.validate_webhook' => false]);
    
    // Mock the event listener to verify it gets called
    Event::fake([TwilioWebhookReceived::class]);
    
    // Send a request to the webhook endpoint
    $response = $this->postJson(config('twilio-laravel.webhook_path'), [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'Body' => 'Test message',
    ]);
    
    // Verify response
    $response->assertStatus(202);
    
    // Verify event was dispatched with correct payload
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->isInboundSms() && 
               $event->payload['From'] === '+12345678901';
    });
}
```

#### Option 3: Integration Testing with Response Expectations

```php
public function test_voice_call_returns_twiml()
{
    // Disable signature validation for testing
    config(['twilio-laravel.validate_webhook' => false]);
    
    // Use real event dispatching to test the full flow
    Event::fake([TwilioWebhookReceived::class]);
    
    // Send a voice webhook
    $response = $this->postJson(config('twilio-laravel.webhook_path'), [
        'CallSid' => 'CA123456',
        'From' => '+12345678901',
        'CallStatus' => 'ringing',
    ]);
    
    // For voice calls, we should get a TwiML response
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml');
    $response->assertSee('<Response>');
}
```

## Security

If you discover any security-related issues, please email citricguy@gmail.com instead of using the issue tracker.

## Credits

- [Josh Sommers](https://github.com/citricguy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
