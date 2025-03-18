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

You can send SMS messages using the `TwilioSms` facade:

```php
use Citricguy\TwilioLaravel\Facades\TwilioSms;

// Basic usage
TwilioSms::sendMessage('+1234567890', 'Hello from Twilio Laravel!');

// With custom from number
TwilioSms::sendMessage(
    '+1234567890',
    'Hello from a different number!',
    ['from' => '+1987654321']
);

// Send immediately (bypass queue)
TwilioSms::sendMessageNow('+1234567890', 'This is urgent!');

// Send MMS with media
TwilioSms::sendMessage(
    '+1234567890',
    'Check out this image!',
    ['mediaUrls' => ['https://example.com/image.jpg']]
);

// Custom queue options
TwilioSms::sendMessage(
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

Or by setting the following environment variables:

### Handling Webhooks

The package includes a webhook handler that automatically validates incoming requests from Twilio and dispatches events that you can listen for in your application.

#### Configuring Webhooks in Twilio

1. Log into your <a href="https://www.twilio.com/console" target="_blank">Twilio Console</a>
2. Navigate to your phone number settings
3. Under "Messaging" or "Voice", set the webhook URL to:
   ```
   https://your-app-url.com/api/twilio/webhook
   ```
   (or whatever path you've configured in `TWILIO_WEBHOOK_PATH`)

#### Listening for Webhook Events

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

Create a listener to handle the webhook:

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
        $type = $event->type;  // 'message', 'voice', or null

        // Handle message webhooks
        if ($type === 'message') {
            $messageSid = $payload['MessageSid'] ?? null;
            $from = $payload['From'] ?? null;
            $body = $payload['Body'] ?? null;
            
            // Process the message...
            Log::info("Received SMS from $from: $body");
        }
        
        // Handle other webhook types as needed
    }
}
```

### Webhook Security

By default, the package validates all incoming webhook requests from Twilio using the signature validation mechanism. This ensures that webhooks are genuinely from Twilio.

You can disable this in development by setting:

```php
// .env file
TWILIO_VALIDATE_WEBHOOK=false
```

This is not recommended for production.

## Webhook Configuration

This package provides a route for receiving and processing Twilio webhooks. By default, the webhook endpoint is configured at `/api/twilio/webhook`, but you can customize this in your configuration.

### Customizing the Webhook Path

You can customize the webhook path by setting the `TWILIO_WEBHOOK_PATH` environment variable in your `.env` file:

```
TWILIO_WEBHOOK_PATH=/your/custom/webhook/path
```

Alternatively, you can publish the configuration file and modify the `webhook_path` setting directly:

```php
'webhook_path' => env('TWILIO_WEBHOOK_PATH', '/api/twilio/webhook'),
```

### Setting Up Twilio to Use Your Webhook

1. Go to the Twilio Console
2. Navigate to your phone number settings
3. In the "Messaging" section, set the webhook URL to your application's URL plus the webhook path:
   ```
   https://your-app-domain.com/api/twilio/webhook
   ```
   (Or your custom path if you've changed it)
4. Select "HTTP POST" as the request method
5. Save your changes

### Webhook Security

All webhooks are automatically validated using Twilio's signature validation process when `TWILIO_VALIDATE_WEBHOOK` is set to `true` (the default). This ensures that requests are genuinely coming from Twilio and haven't been tampered with.

You can disable validation in development environments by setting:

```
TWILIO_VALIDATE_WEBHOOK=false
```

### Testing Webhooks

When writing tests that interact with the webhook endpoint, make sure to use the configured path from the config:

```php
// In your tests
$webhookPath = config('twilio-laravel.webhook_path');
$response = $this->postJson($webhookPath, [...]);
```

This ensures your tests will continue to work even if you change the webhook path configuration.

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
2. Use the included test helpers to simulate webhook calls
3. Assert that your listeners process the events correctly

## Security

If you discover any security-related issues, please email citricguy@gmail.com instead of using the issue tracker.

## Credits

- <a href="https://github.com/citricguy" target="_blank">Josh Sommers</a>
- <a href="../../contributors" target="_blank">All Contributors</a>

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
