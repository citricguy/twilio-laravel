<?php

namespace Citricguy\TwilioLaravel\Services;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
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
            // Fire the sending event and allow for cancellation
            $sendingEvent = new TwilioMessageSending($to, $message, $options);
            event($sendingEvent);
            
            // Check if the message was cancelled
            if ($sendingEvent->cancelled()) {
                if (config('twilio-laravel.debug', false)) {
                    Log::info('Twilio: Message cancelled', [
                        'to' => $to,
                        'reason' => $sendingEvent->cancellationReason(),
                    ]);
                }
                
                return [
                    'status' => 'cancelled',
                    'to' => $to,
                    'reason' => $sendingEvent->cancellationReason(),
                ];
            }
            
            $messageData = [
                'body' => $message,
                'to' => $to,
            ];

            // Validate and set sender
            if (! empty($options['from'])) {
                $messageData['from'] = $options['from'];
            } elseif (! empty(config('twilio-laravel.messaging_service_sid'))) {
                $messageData['messagingServiceSid'] = config('twilio-laravel.messaging_service_sid');
            } elseif (! empty(config('twilio-laravel.from'))) {
                $messageData['from'] = config('twilio-laravel.from');
            } else {
                throw new \Exception('No valid sender configured for Twilio.');
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
            $messageResponse = $this->client->messages->create($to, $messageData);

            // Ensure body is string safe for calculation
            $segmentsCount = ceil(mb_strlen((string) $messageData['body']) / 153);

            // Fire the sent event
            event(new TwilioMessageSent(
                $messageResponse->sid,
                $to,
                $messageData['body'],
                $messageResponse->status,
                $segmentsCount,
                $options
            ));

            return $messageResponse;
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
        // Fire the sending event and allow for cancellation
        $sendingEvent = new TwilioMessageSending($to, $message, $options);
        event($sendingEvent);
        
        // Check if the message was cancelled
        if ($sendingEvent->cancelled()) {
            if (config('twilio-laravel.debug', false)) {
                Log::info('Twilio: Message cancelled', [
                    'to' => $to,
                    'reason' => $sendingEvent->cancellationReason(),
                ]);
            }
            
            return [
                'status' => 'cancelled',
                'to' => $to,
                'reason' => $sendingEvent->cancellationReason(),
            ];
        }
        
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
