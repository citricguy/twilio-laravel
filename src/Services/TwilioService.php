<?php

namespace Citricguy\TwilioLaravel\Services;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class TwilioService
{
    /**
     * Twilio client instance.
     *
     * @var \Twilio\Rest\Client
     */
    protected $client;

    /**
     * Create a new TwilioService instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new TwilioClient(
            config('twilio-laravel.account_sid'),
            config('twilio-laravel.auth_token')
        );
    }

    /**
     * Send an SMS message.
     *
     * @return mixed
     */
    public function sendMessage(string $to, string $message, array $options = [])
    {
        // Use the queue if enabled in config
        if (config('twilio-laravel.queue_messages', true)) {
            return $this->queueMessage($to, $message, $options);
        }

        return $this->sendMessageNow($to, $message, $options);
    }

    /**
     * Send an SMS message immediately.
     *
     * @return mixed
     */
    public function sendMessageNow(string $to, string $message, array $options = [])
    {
        try {
            $messageData = [
                'body' => $message,
                'to' => $to,
            ];

            // Use the from number provided in options, or fall back to config
            if (isset($options['from'])) {
                $messageData['from'] = $options['from'];
            } elseif (config('twilio-laravel.messaging_service_sid')) {
                $messageData['messagingServiceSid'] = config('twilio-laravel.messaging_service_sid');
            } else {
                $messageData['from'] = config('twilio-laravel.from');
            }

            // Add media URLs if provided
            if (! empty($options['mediaUrls'])) {
                $messageData['mediaUrl'] = $options['mediaUrls'];
            }

            // Debug logging
            if (config('twilio-laravel.debug', false)) {
                Log::debug('Twilio: Sending message', ['to' => $to, 'options' => $options]);
            }

            // Send the message
            $message = $this->client->messages->create($to, $messageData);

            // Calculate segments
            $segmentsCount = ceil(mb_strlen($messageData['body']) / 153);

            // Fire the sent event
            event(new TwilioMessageSent(
                $message->sid,
                $to,
                $messageData['body'],
                $message->status,
                $segmentsCount,
                $options
            ));

            return $message;
        } catch (\Exception $e) {
            if (config('twilio-laravel.debug', false)) {
                Log::error('Twilio: Failed to send message', [
                    'error' => $e->getMessage(),
                    'to' => $to,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Queue an SMS message for sending.
     *
     * @return array
     */
    public function queueMessage(string $to, string $message, array $options = [])
    {
        $queueName = $options['queue'] ?? config('twilio-laravel.queue_name', 'default');
        $delay = $options['delay'] ?? null;

        // Calculate segments (for logging/events)
        $segmentsCount = ceil(mb_strlen($message) / 153);

        // Create job
        $job = new SendTwilioMessage($to, $message, $options);

        // Queue with optional delay
        if ($delay) {
            $job->delay($delay)->onQueue($queueName);
        } else {
            $job->onQueue($queueName);
        }

        // Dispatch the job
        dispatch($job);

        // Fire the queued event
        event(new TwilioMessageQueued(
            $to,
            $message,
            'queued',
            $segmentsCount,
            $options
        ));

        return ['status' => 'queued', 'to' => $to];
    }
}
