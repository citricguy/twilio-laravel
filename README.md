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
    - [What are Twilio Webhooks?](#what-are-twilio-webhooks)
    - [Webhook Types](#webhook-types)
    - [Webhook Helper Methods](#webhook-helper-methods)
    - [Handling Different Webhook Types](#handling-different-webhook-types)
  - [Setting Up Twilio Webhooks](#setting-up-twilio-webhooks)
    - [Step 1: Create a Webhook Endpoint](#step-1-create-a-webhook-endpoint)
    - [Step 2: Make Your Endpoint Accessible](#step-2-make-your-endpoint-accessible)
    - [Step 3: Configure Twilio to Send Webhooks](#step-3-configure-twilio-to-send-webhooks)
    - [Step 4: Test Your Webhook](#step-4-test-your-webhook)
  - [Webhook Security](#webhook-security)
    - [Webhook Validation Explained](#webhook-validation-explained)
  - [Verification Tools](#verification-tools)
  - [Helper Functions](#helper-functions)
    - [WebhookSignatureHelper](#webhooksignaturehelper)
  - [Testing](#testing)
    - [Testing Webhook Functionality](#testing-webhook-functionality)
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

### What are Twilio Webhooks?

Webhooks are HTTP callbacks that Twilio sends to your application when certain events occur. For example:
- When someone sends an SMS to your Twilio phone number
- When the status of a message changes (delivered, failed, etc.)
- When an incoming voice call is received

This package provides a simple way to receive these webhooks and process them in your Laravel application.

### Webhook Types

The package automatically detects different types of Twilio webhooks and categorizes them to make handling easier. The webhook types are:

| Type | Description | Example Scenario |
|------|-------------|------------------|
| `message-inbound-sms` | Incoming SMS message | Someone texts your Twilio number |
| `message-inbound-mms` | Incoming MMS message | Someone sends media to your Twilio number |
| `message-status-*` | Message status updates | Your sent message is delivered or fails |
| `voice-inbound` | Incoming voice call | Someone calls your Twilio number |
| `voice-status` | Voice call status updates | A call ends or changes status |

### Webhook Helper Methods

The `TwilioWebhookReceived` event provides helper methods to easily identify webhook types:

```php
// High-level webhook categorization
$event->isVoiceWebhook();  // Check if this is any type of voice webhook
$event->isMessageWebhook(); // Check if this is any type of message webhook

// Check for inbound messages (SMS or MMS)
$event->isInboundMessage();
$event->isInboundVoiceCall(); // Check specifically for inbound voice calls
  
// Specifically check for SMS vs MMS
$event->isInboundSms();
$event->isInboundMms();
  
// Check for status updates
$event->isMessageStatusUpdate();
$event->isVoiceStatusUpdate();
$event->isStatusUpdate(); // Any type of status update
  
// Get the status value (e.g., "delivered", "failed", etc.)
// Note: Status values are normalized to lowercase
$event->getStatusType();
```

### Handling Different Webhook Types

You can listen for webhook events in your `EventServiceProvider`:

```php
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

protected $listen = [
    // ... other event listeners
    TwilioWebhookReceived::class => [
        App\Listeners\HandleTwilioWebhook::class,
    ],
];
```

Create a listener to handle different webhook types:

```php
namespace App\Listeners;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Support\Facades\Log;

class HandleTwilioWebhook
{
    public function handle(TwilioWebhookReceived $event)
    {
        // Access webhook data
        $payload = $event->payload;
        
        // Quick check for webhook category
        if ($event->isMessageWebhook()) {
            Log::info('Received a message-related webhook');
        } else if ($event->isVoiceWebhook()) {
            Log::info('Received a voice-related webhook');
        }
        
        // More specific handling
        
        // Handle incoming SMS and MMS
        if ($event->isInboundMessage()) {
            $from = $payload['From'] ?? null;
            $body = $payload['Body'] ?? null;
            
            if ($event->isInboundMms()) {
                // Process incoming MMS with media
                $mediaCount = $payload['NumMedia'] ?? 0;
                $mediaUrls = [];
                
                for ($i = 0; $i < intval($mediaCount); $i++) {
                    $mediaUrls[] = $payload["MediaUrl$i"] ?? null;
                }
                
                Log::info("Received MMS from $from with $mediaCount media items");
                // Process the media...
            } else {
                // Process regular SMS
                Log::info("Received SMS from $from: $body");
                // Respond to the SMS...
            }
        }
        
        // Handle message status updates
        else if ($event->isMessageStatusUpdate()) {
            $messageSid = $payload['MessageSid'] ?? null;
            $status = $event->getStatusType(); // e.g., "delivered", "failed"
            
            Log::info("Message $messageSid status: $status");
            
            if ($status === 'failed') {
                // Handle failed messages
            }
        }
        
        // Handle voice calls
        else if ($event->isInboundVoiceCall()) {
            $callSid = $payload['CallSid'] ?? null;
            $from = $payload['From'] ?? null;
            
            Log::info("Incoming call from $from (SID: $callSid)");
            // Handle the incoming call...
        }
        
        // Handle voice status updates
        else if ($event->isVoiceStatusUpdate()) {
            $callSid = $payload['CallSid'] ?? null;
            $status = $event->getStatusType(); // e.g., "completed", "busy"
            
            Log::info("Call $callSid status: $status");
        }
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

```php
// .env file
TWILIO_VALIDATE_WEBHOOK=false
```

This is not recommended for production.

### Webhook Validation Explained

When Twilio sends a webhook, it includes an `X-Twilio-Signature` header that's generated based on:
- Your Twilio auth token
- The full URL of your webhook endpoint
- The request parameters

The package verifies this signature to ensure the request is legitimate.

## Verification Tools

The package includes a helpful command to verify your webhook setup:

```bash
php artisan twilio:verify-webhook-setup --url=https://your-production-domain.com
```

This command will:
- Check if your auth token is configured
- Verify your webhook path settings
- Display the full webhook URL to configure in Twilio
- Confirm if signature validation is enabled

## Helper Functions

### WebhookSignatureHelper

The package includes utilities to help with webhook signature validation:

```php
use Citricguy\TwilioLaravel\Helpers\WebhookSignatureHelper;

// Generate a signature for testing
$signature = WebhookSignatureHelper::generateValidSignature(
    'https://your-url.com/api/twilio/webhook',
    ['MessageSid' => 'SM123456'],
    'your-auth-token'
);

// Verify a signature
$isValid = WebhookSignatureHelper::isValidSignature(
    $signatureFromHeader,
    'https://your-url.com/api/twilio/webhook',
    $requestParams,
    'your-auth-token'
);
```

## Testing

```bash
composer test
```

The package includes comprehensive tests for the webhook handling system, including middleware validation tests.

### Testing Webhook Functionality

To test your application's handling of Twilio webhooks:

1. Set `TWILIO_VALIDATE_WEBHOOK=false` in your testing environment
2. Use the WebhookSignatureHelper to simulate webhook calls if necessary
3. Create test cases for each webhook type you need to handle

Example test for handling an incoming SMS:

```php
public function test_can_handle_incoming_sms()
{
    Event::fake();
    
    $webhookPath = config('twilio-laravel.webhook_path');
    
    $this->postJson($webhookPath, [
        'MessageSid' => 'SM123456',
        'From' => '+12345678901',
        'Body' => 'Test message'
    ]);
    
    Event::assertDispatched(TwilioWebhookReceived::class, function ($event) {
        return $event->isInboundSms() && 
               $event->payload['From'] === '+12345678901';
    });
}
```

## Security

If you discover any security-related issues, please email citricguy@gmail.com instead of using the issue tracker.

## Credits

- [Josh Sommers](https://github.com/citricguy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
