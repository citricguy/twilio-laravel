<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Twilio Credentials
    |--------------------------------------------------------------------------
    |
    | These values are your Twilio Account SID and Auth Token. It’s best practice
    | to store these in your .env file so they’re not hard-coded into your codebase.
    |
    */
    'account_sid' => env('TWILIO_SID', 'your-twilio-sid'),
    'auth_token'  => env('TWILIO_TOKEN', 'your-twilio-auth-token'),

    /*
    |--------------------------------------------------------------------------
    | Default Sender
    |--------------------------------------------------------------------------
    |
    | This is the default phone number that will be used when sending messages.
    | You can override it on a per-message basis if needed.
    |
    */
    'from'  => env('TWILIO_FROM', '+1234567890'),

    /*
    |--------------------------------------------------------------------------
    | Messaging Service SID (Optional)
    |--------------------------------------------------------------------------
    |
    | If you’re using a messaging service in Twilio, you can specify its SID here.
    | Otherwise, you can leave it as null.
    |
    */
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID', null),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | This specifies the Twilio API version you want to use. Generally, the default
    | version is fine unless you have a specific need to change it.
    |
    */
    'api_version' => '2010-04-01',

    /*
    |--------------------------------------------------------------------------
    | Webhook Validation
    |--------------------------------------------------------------------------
    |
    | Enable or disable the Twilio webhook signature validation. This can be useful
    | if you need to bypass validation in certain development environments.
    |
    */
    'validate_webhook' => env('TWILIO_VALIDATE_WEBHOOK', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Path
    |--------------------------------------------------------------------------
    |
    | This is the URL path where Twilio webhooks will be received.
    | For example: 'webhooks/twilio' would be accessed at yourdomain.com/webhooks/twilio
    |
    */
    'webhook_path' => env('TWILIO_WEBHOOK_PATH', '/api/twilio/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Message Queue Settings
    |--------------------------------------------------------------------------
    |
    | These settings determine how Twilio messages are queued.
    |
    */
    'queue_messages' => env('TWILIO_QUEUE_MESSAGES', true),
    'queue_name' => env('TWILIO_QUEUE', 'default'),
    'queue_retries' => env('TWILIO_QUEUE_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode to log additional information during development. This
    | setting is useful for troubleshooting issues.
    |
    */
    'debug' => env('TWILIO_DEBUG', false),
];
