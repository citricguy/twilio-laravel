# Twilio for Laravel (Unoffical)

<p align="center">
  <a href="https://packagist.org/packages/citricguy/twilio-laravel" target="_blank">
    <img src="https://img.shields.io/packagist/v/citricguy/twilio-laravel.svg?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/citricguy/twilio-laravel" target="_blank">
    <img src="https://img.shields.io/packagist/dt/citricguy/twilio-laravel.svg?style=flat-square" alt="Total Downloads">
  </a>
</p>

A Laravel package to integrate Twilio for SMS/MMS messaging, voice calls, notifications, and webhooks. This package leverages the official Twilio PHP SDK and adheres to Laravel conventions, providing a seamless, queued, and event-driven solution for sending messages, making calls, and processing incoming Twilio callbacks.

## 📋 Table of Contents

- [Twilio for Laravel (Unoffical)](#twilio-for-laravel-unoffical)
  - [📋 Table of Contents](#-table-of-contents)
  - [Installation](#installation)
  - [Configuration](#configuration)
    - [Sender Configuration](#sender-configuration)
    - [Debug Mode](#debug-mode)
    - [Notification Channel Configuration](#notification-channel-configuration)
  - [Usage](#usage)
    - [Sending SMS Messages](#sending-sms-messages)
      - [Message Queuing](#message-queuing)
    - [Making Voice Calls](#making-voice-calls)
  - [Working with Webhooks](#working-with-webhooks)
    - [Webhook Types](#webhook-types)
    - [Webhook Helper Methods](#webhook-helper-methods)
    - [Handling Different Webhook Types](#handling-different-webhook-types)
    - [How Responses Work](#how-responses-work)
  - [Events](#events)
    - [Message Events](#message-events)
      - [TwilioMessageSending Example](#twiliomessagesending-example)
      - [TwilioMessageQueued Example](#twiliomessagequeued-example)
      - [TwilioMessageSent Example](#twiliomessagesent-example)
    - [Call Events](#call-events)
      - [TwilioCallSending Example](#twiliocallsending-example)
      - [TwilioWebhookReceived Example](#twiliowebhookreceived-example)
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
    - [Faking the Twilio Facade](#faking-the-twilio-facade)
    - [Testing Voice Calls](#testing-voice-calls)
    - [Testing Your Webhook Handlers](#testing-your-webhook-handlers)
      - [Option 1: Dispatching the Event Directly](#option-1-dispatching-the-event-directly)
      - [Option 2: Testing the HTTP Endpoint](#option-2-testing-the-http-endpoint)
      - [Option 3: Integration Testing with Response Expectations](#option-3-integration-testing-with-response-expectations)
  - [Using the Notification Channel](#using-the-notification-channel)
    - [Configuring Your Notifiable Model](#configuring-your-notifiable-model)
    - [Creating a Notification](#creating-a-notification)
    - [Sending SMS Notifications](#sending-sms-notifications)
    - [Sending MMS Notifications](#sending-mms-notifications)
    - [Additional Options](#additional-options)
    - [Simple String Responses](#simple-string-responses)
    - [Event Tracking](#event-tracking)
  - [Using the Call Notification Channel](#using-the-call-notification-channel)
    - [Configuring Your Notifiable Model](#configuring-your-notifiable-model-1)
    - [Creating a Call Notification](#creating-a-call-notification)
    - [Sending Call Notifications](#sending-call-notifications)
    - [Additional Call Options](#additional-call-options)
    - [Simple String URL Responses](#simple-string-url-responses)
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
# If using a Messaging Service instead of a sender phone number
TWILIO_MESSAGING_SERVICE_SID=your-messaging-service-sid
```

### Sender Configuration

When sending messages, the package determines the sender using the following priority order:

1. The `from` parameter in the options array passed to the `sendMessage` method
2. The messaging service SID in the options array (`messagingServiceSid`)
3. The messaging service SID from your config (set via `TWILIO_MESSAGING_SERVICE_SID` in .env)
4. The default sender number from your config (set via `TWILIO_FROM` in .env)

If none of these are provided, an exception will be thrown indicating that no valid sender is configured.

Example usage with different sender options:
```php
// Uses the 'from' parameter directly
Twilio::sendMessage('+1234567890', 'Message with custom from', ['from' => '+15551234567']);

// Uses a specific messaging service
Twilio::sendMessage('+1234567890', 'Message via messaging service', ['messagingServiceSid' => 'MG123456789']);

// Uses the default configuration from .env
Twilio::sendMessage('+1234567890', 'Message with default sender');
```

### Debug Mode

The `TWILIO_DEBUG` environment variable can be used to enable or disable debug mode. When debug mode is enabled (`TWILIO_DEBUG=true`), additional logging and debugging information will be available to help troubleshoot issues. It is recommended to keep debug mode disabled (`TWILIO_DEBUG=false`) in production environments.

### Notification Channel Configuration

The package registers two notification channels:

1. `twilioSms` - For sending SMS/MMS notifications
2. `twilioCall` - For initiating voice calls

You can customize the channel names in the configuration:

```php
// In config/twilio-laravel.php
'notifications' => [
    'channel_name' => 'twilioSms',     // SMS notifications channel
    'call_channel_name' => 'twilioCall',  // Call notifications channel
],
```

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

// With status callback URL
Twilio::sendMessage(
    '+1234567890',
    'Track message delivery!',
    ['StatusCallback' => 'https://yourdomain.com/webhooks/twilio/status-updates']
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

### Making Voice Calls

You can initiate voice calls using the `Twilio` facade:

```php
use Citricguy\TwilioLaravel\Facades\Twilio;

// Basic voice call - requires a URL that returns TwiML instructions
Twilio::makeCall('+1234567890', 'https://example.com/twiml');

// With custom from number
Twilio::makeCall(
    '+1234567890',
    'https://example.com/twiml',
    ['from' => '+1987654321']
);

// Make an immediate call (bypass queue)
Twilio::makeCallNow('+1234567890', 'https://example.com/twiml');

// With status callback URL
Twilio::makeCall(
    '+1234567890',
    'https://example.com/twiml',
    ['statusCallback' => 'https://yourdomain.com/webhooks/twilio/call-status']
);

// With status callback events
Twilio::makeCall(
    '+1234567890',
    'https://example.com/twiml',
    [
        'statusCallback' => 'https://yourdomain.com/webhooks/twilio/call-status',
        'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed']
    ]
);

// Enable call recording
Twilio::makeCall(
    '+1234567890',
    'https://example.com/twiml',
    ['record' => true]
);

// Custom queue options
Twilio::makeCall(
    '+1234567890',
    'https://example.com/twiml',
    [
        'queue' => 'high-priority',
        'delay' => 30 // delay in seconds
    ]
);
```

Voice calls require a URL that returns TwiML instructions. The TwiML document controls what happens during the call (playing messages, gathering input, etc.). Your application is responsible for creating and serving these TwiML documents.

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

## Events

This package provides several events you can listen for in your application:

### Message Events

| Event | Description | Properties |
|-------|-------------|------------|
| `TwilioMessageSending` | Fired before a message is sent - can be cancelled | `to`, `message`, `options` |
| `TwilioMessageQueued` | Fired when a message is queued for sending | `to`, `message`, `status`, `segmentsCount`, `options` |
| `TwilioMessageSent` | Fired when a message is sent successfully | `messageSid`, `to`, `message`, `status`, `segmentsCount`, `options` |

You can listen for these events in your `EventServiceProvider`:

```php
protected $listen = [
    \Citricguy\TwilioLaravel\Events\TwilioMessageSending::class => [
        \App\Listeners\ValidateMessageBeforeSending::class,
    ],
    \Citricguy\TwilioLaravel\Events\TwilioMessageSent::class => [
        \App\Listeners\LogTwilioMessageSent::class,
    ],
    \Citricguy\TwilioLaravel\Events\TwilioMessageQueued::class => [
        \App\Listeners\LogTwilioMessageQueued::class,
    ],
    \Citricguy\TwilioLaravel\Events\TwilioWebhookReceived::class => [
        \App\Listeners\HandleTwilioWebhook::class,
    ],
];
```

#### TwilioMessageSending Example

The `TwilioMessageSending` event allows you to implement validation logic before a message is sent. You can cancel the message if it doesn't meet your criteria:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use App\Models\BlockedNumber;
use App\Services\PhoneNumberService;

class ValidateMessageBeforeSending
{
    protected $phoneNumberService;
    
    public function __construct(PhoneNumberService $phoneNumberService)
    {
        $this->phoneNumberService = $phoneNumberService;
    }
    
    public function handle(TwilioMessageSending $event)
    {
        $to = $event->to;
        
        // Example 1: Check against database of blocked numbers
        if (BlockedNumber::where('phone_number', $to)->exists()) {
            return $event->cancel('Number is in blocked list');
        }
        
        // Example 2: Check area code restrictions
        $areaCode = $this->phoneNumberService->getAreaCode($to);
        $blockedAreaCodes = ['555', '900'];
        
        if (in_array($areaCode, $blockedAreaCodes)) {
            return $event->cancel('Area code is not allowed');
        }
        
        // Example 3: Check for international numbers if not allowed
        if (!config('app.allow_international_sms') && !$this->phoneNumberService->isDomestic($to)) {
            return $event->cancel('International messages are disabled');
        }
        
        // Example 4: Rate limiting
        if ($this->phoneNumberService->isRateLimited($to)) {
            return $event->cancel('Rate limit exceeded for this number');
        }
    }
}
```

When a message is cancelled:
- The `sendMessage` or `sendMessageNow` method returns an array with:
  - `status` => 'cancelled'
  - `to` => the recipient number
  - `reason` => the optional cancellation reason provided

#### TwilioMessageQueued Example

The `TwilioMessageQueued` event is fired when a message is successfully queued for later sending:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Log;

class LogTwilioMessageQueued
{
    public function handle(TwilioMessageQueued $event)
    {
        // Example 1: Record in database for tracking/analytics
        SmsMessage::create([
            'to' => $event->to,
            'message' => $event->message,
            'status' => 'queued',
            'segments' => $event->segmentsCount,
            'queued_at' => now(),
        ]);
        
        // Example 2: Add custom logging
        Log::channel('sms')->info('SMS queued for sending', [
            'to' => $event->to,
            'segments' => $event->segmentsCount,
            'queue' => $event->options['queue'] ?? 'default',
        ]);
        
        // Example 3: Notify admin of high-priority messages
        if (!empty($event->options['priority']) && $event->options['priority'] === 'high') {
            // Send notification to admin dashboard
            event(new \App\Events\HighPrioritySmsQueued($event->to, $event->message));
        }
    }
}
```

#### TwilioMessageSent Example

The `TwilioMessageSent` event is fired when a message is actually sent to Twilio's API:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use App\Models\SmsMessage;
use App\Services\BillingService;

class LogTwilioMessageSent
{
    protected $billingService;
    
    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }
    
    public function handle(TwilioMessageSent $event)
    {
        // Example 1: Update message status in database
        SmsMessage::updateOrCreate(
            ['to' => $event->to, 'status' => 'queued'],
            [
                'message_sid' => $event->messageSid,
                'status' => $event->status,
                'segments' => $event->segmentsCount,
                'sent_at' => now(),
            ]
        );
        
        // Example 2: Track SMS costs for customer billing
        $this->billingService->trackMessageCost(
            $event->messageSid,
            $event->to,
            $event->segmentsCount,
            $event->options['customer_id'] ?? null
        );
        
        // Example 3: Record analytics
        app('analytics')->trackEvent('sms_sent', [
            'to' => $event->to,
            'segments' => $event->segmentsCount,
            'status' => $event->status
        ]);
    }
}
```

### Call Events

| Event | Description | Properties |
|-------|-------------|------------|
| `TwilioCallSending` | Fired before a call is initiated - can be cancelled | `to`, `url`, `options` |
| `TwilioCallQueued` | Fired when a call is queued | `to`, `url`, `status`, `options` |
| `TwilioCallSent` | Fired when a call is successfully initiated | `callSid`, `to`, `url`, `status`, `options` |

You can listen for these events in your `EventServiceProvider`:

```php
protected $listen = [
    \Citricguy\TwilioLaravel\Events\TwilioCallSending::class => [
        \App\Listeners\ValidateCallBeforeInitiating::class,
    ],
    \Citricguy\TwilioLaravel\Events\TwilioCallSent::class => [
        \App\Listeners\LogTwilioCallSent::class,
    ],
    \Citricguy\TwilioLaravel\Events\TwilioCallQueued::class => [
        \App\Listeners\LogTwilioCallQueued::class,
    ],
];
```

#### TwilioCallSending Example

Similar to message sending, the `TwilioCallSending` event allows you to implement validation logic before a call is initiated:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use App\Models\BlockedNumber;
use App\Services\PhoneNumberService;

class ValidateCallBeforeInitiating
{
    protected $phoneNumberService;
    
    public function __construct(PhoneNumberService $phoneNumberService)
    {
        $this->phoneNumberService = $phoneNumberService;
    }
    
    public function handle(TwilioCallSending $event)
    {
        $to = $event->to;
        
        // Example: Check against database of blocked numbers
        if (BlockedNumber::where('phone_number', $to)->exists()) {
            return $event->cancel('Number is in blocked list');
        }
        
        // Example: Check time restrictions
        $currentHour = now()->hour;
        if ($currentHour < 8 || $currentHour > 20) {
            return $event->cancel('Calls are only allowed between 8 AM and 8 PM');
        }
    }
}
```

When a call is cancelled:
- The `makeCall` or `makeCallNow` method returns an array with:
  - `status` => 'cancelled'
  - `to` => the recipient number
  - `reason` => the optional cancellation reason provided

#### TwilioWebhookReceived Example

The `TwilioWebhookReceived` event is fired when a webhook is received from Twilio:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use App\Models\SmsMessage;
use App\Models\Conversation;

class HandleTwilioWebhook
{
    public function handle(TwilioWebhookReceived $event)
    {
        // Example 1: Handle incoming SMS
        if ($event->isInboundSms()) {
            // Store the incoming message
            $incomingSms = [
                'from' => $event->payload['From'],
                'body' => $event->payload['Body'],
                'sid' => $event->payload['MessageSid'],
            ];
            
            // Create or update a conversation
            $conversation = Conversation::firstOrCreate(['phone_number' => $incomingSms['from']]);
            $conversation->messages()->create([
                'direction' => 'inbound',
                'body' => $incomingSms['body'],
                'message_sid' => $incomingSms['sid'],
            ]);
            
            // Potentially trigger automated response
            // ...
        }
        
        // Example 2: Handle delivery status updates
        if ($event->isMessageStatusUpdate()) {
            $messageSid = $event->payload['MessageSid'];
            $status = $event->getStatusType();
            
            // Update message status in your database
            SmsMessage::where('message_sid', $messageSid)
                ->update([
                    'status' => $status,
                    'status_updated_at' => now(),
                ]);
            
            // If it's a failure, you might want to alert someone
            if (in_array($status, ['failed', 'undelivered'])) {
                // Notify admins or trigger a retry
                // ...
            }
        }
        
        // Example 3: Handle voice calls with TwiML response
        if ($event->isInboundVoiceCall()) {
            // Generate TwiML response
            return response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <Response>
                    <Say>Thank you for calling. Our representatives are currently busy.</Say>
                    <Gather numDigits="1" timeout="10" action="/api/twilio/menu">
                        <Say>Press 1 for sales, press 2 for support.</Say>
                    </Gather>
                </Response>',
                200,
                ['Content-Type' => 'text/xml']
            );
        }
    }
}
```

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

### Faking the Twilio Facade

During testing, you'll typically want to avoid making actual API calls to Twilio. The package provides a way to fake the Twilio facade:

```php
use Citricguy\TwilioLaravel\Facades\Twilio;

// In your test method or setUp
Twilio::fake();

// Now any calls to Twilio::sendMessage() won't actually call the Twilio API
Twilio::sendMessage('+1234567890', 'Test message');

// You can make assertions on the messages that would have been sent
Twilio::assertSent(function ($message) {
    return $message->to === '+1234567890' &&
           $message->body === 'Test message';
});

// Assert a message was sent to a specific number
Twilio::assertSentTo('+1234567890');

// Assert a specific number of messages were sent
Twilio::assertSentCount(1);

// Assert no messages were sent
Twilio::assertNothingSent();
```

This allows you to test code that uses the Twilio facade without actually sending messages or making API calls.

### Testing Voice Calls

During testing, you can use the fake Twilio implementation to verify calls:

```php
use Citricguy\TwilioLaravel\Facades\Twilio;

// In your test method or setUp
Twilio::fake();

// Now any calls to Twilio::makeCall() won't actually call the Twilio API
Twilio::makeCall('+1234567890', 'https://example.com/twiml');

// You can make assertions on the calls that would have been made
Twilio::assertCallMade(function ($call) {
    return $call->to === '+1234567890' &&
           $call->url === 'https://example.com/twiml';
});

// Assert a call was made to a specific number
Twilio::assertCalledTo('+1234567890');

// Assert a specific number of calls were made
Twilio::assertCallCount(1);

// Assert no calls were made
Twilio::assertNoCalls();
```

This allows you to test code that initiates calls without actually making API calls to Twilio.

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

## Using the Notification Channel

This package includes a Laravel Notification Channel for sending SMS and MMS through Twilio. This follows Laravel's notification system patterns and allows for easy integration with your existing notification architecture.

### Configuring Your Notifiable Model

Ensure your model includes a method to determine the phone number to receive SMS:

```php
public function routeNotificationForTwilioSms($notification)
{
    return $this->phone_number; // Replace with your phone field
}
```

### Creating a Notification

Create a notification with a `toTwilioSms` method:

```php
namespace App\Notifications;

use Citricguy\TwilioLaravel\Notifications\TwilioSmsMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification
{
    protected $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['twilioSms'];
    }

    public function toTwilioSms($notifiable)
    {
        return (new TwilioSmsMessage)
            ->content("Your verification code is {$this->code}")
            ->from('+1234567890'); // Optional custom sender
    }
}
```

### Sending SMS Notifications

You can send notifications using Laravel's built-in notification system:

```php
$user->notify(new VerificationCodeNotification('1234'));
```

### Sending MMS Notifications

You can include media in your notifications by using the `mediaUrls` method:

```php
public function toTwilioSms($notifiable)
{
    return (new TwilioSmsMessage)
        ->content('Check out this image!')
        ->mediaUrls(['https://example.com/image.jpg']);
}
```

### Additional Options

The `TwilioSmsMessage` class provides a fluent interface for setting various options:

```php
public function toTwilioSms($notifiable)
{
    return (new TwilioSmsMessage)
        ->content('Your message here')
        ->from('+1234567890')                                // Custom sender number
        ->messagingService('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX') // Or use messaging service
        ->mediaUrls(['https://example.com/image.jpg'])       // For MMS
        ->statusCallback('https://example.com/webhook/status') // Status callback URL
        ->options([                                         // Additional custom options
            'delay' => 30, // delay in seconds
            'queue' => 'high-priority',
        ]);
}
```

### Simple String Responses

For simple notifications, you can return a string directly:

```php
public function toTwilioSms($notifiable)
{
    return "Your verification code is {$this->code}";
}
```

### Event Tracking

When sending SMS through the notification channel, the package automatically adds notification metadata to the event options, allowing you to identify messages sent through the notification system in your event listeners:

```php
// In your event listener
public function handle(TwilioMessageSent $event)
{
    if (isset($event->options['_notification'])) {
        // This message was sent via a notification
        $notificationType = $event->options['_notification']['type'];
        $notifiableType = $event->options['_notification']['notifiable'];
        $notifiableId = $event->options['_notification']['notifiable_id'];
        
        // Do notification-specific processing
    }
}
```

## Using the Call Notification Channel

This package includes a Laravel Notification Channel for initiating voice calls through Twilio.

### Configuring Your Notifiable Model

Ensure your model includes a method to determine the phone number to receive calls:

```php
public function routeNotificationForTwilioCall($notification)
{
    return $this->phone_number; // Replace with your phone field
}
```

### Creating a Call Notification

Create a notification with a `toTwilioCall` method:

```php
namespace App\Notifications;

use Citricguy\TwilioLaravel\Notifications\TwilioCallMessage;
use Illuminate\Notifications\Notification;

class EmergencyAlertNotification extends Notification
{
    public function via($notifiable)
    {
        return ['twilioCall'];
    }

    public function toTwilioCall($notifiable)
    {
        return (new TwilioCallMessage)
            ->url('https://example.com/emergency-twiml')
            ->from('+1234567890'); // Optional custom caller
    }
}
```

### Sending Call Notifications

You can send notifications using Laravel's built-in notification system:

```php
$user->notify(new EmergencyAlertNotification());
```

### Additional Call Options

The `TwilioCallMessage` class provides a fluent interface for setting various options:

```php
public function toTwilioCall($notifiable)
{
    return (new TwilioCallMessage)
        ->url('https://example.com/twiml-instructions')
        ->from('+1234567890')                               // Custom caller number
        ->statusCallback('https://example.com/call-status')  // Status callback URL
        ->statusCallbackEvent(['initiated', 'completed'])    // Which events to receive
        ->record(true)                                       // Record the call
        ->timeout(30)                                        // Call timeout in seconds
        ->options([                                          // Additional custom options
            'delay' => 30,     // delay in seconds
            'queue' => 'high-priority',
        ]);
}
```

### Simple String URL Responses

For simple notifications, you can return a string URL directly:

```php
public function toTwilioCall($notifiable)
{
    return "https://example.com/twiml-instructions";
}
```

## Security

If you discover any security-related issues, please email citricguy@gmail.com instead of using the issue tracker.

## Credits

- [Josh Sommers](https://github.com/citricguy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
