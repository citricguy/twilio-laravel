<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Twilio API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Twilio Account SID and Auth Token from your Twilio dashboard
    |
    */
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Sender Phone Number or Messaging Service SID
    |--------------------------------------------------------------------------
    |
    | This is the default number that will be used to send messages from or
    | your Messaging Service SID if using a messaging service.
    |
    */
    'from' => env('TWILIO_FROM'),
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Authentication
    |--------------------------------------------------------------------------
    |
    | Set this to false if you don't want to authenticate incoming Twilio webhook
    | requests. The default is true and the middleware will validate that requests
    | are genuinely from Twilio using the signature in the request header.
    |
    */
    'validate_webhook_signature' => env('TWILIO_VALIDATE_WEBHOOK_SIGNATURE', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Path
    |--------------------------------------------------------------------------
    |
    | The URI path where Twilio webhooks will be received
    |
    */
    'webhook_path' => env('TWILIO_WEBHOOK_PATH', 'webhooks/twilio'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Messages and calls are queued by default for better performance.
    | You can disable queuing or customize the queue name.
    |
    */
    'queue_messages' => env('TWILIO_QUEUE_MESSAGES', true),
    'queue_name' => env('TWILIO_QUEUE_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, detailed logs about API calls and message/call processing will be output
    |
    */
    'debug' => env('TWILIO_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Notification Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the names of the notification channels
    |
    */
    'notifications' => [
        'channel_name' => 'twilioSms',     // SMS notifications channel
        'call_channel_name' => 'twilioCall',  // Call notifications channel
    ],
];
