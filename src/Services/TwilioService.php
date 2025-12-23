<?php

namespace Citricguy\TwilioLaravel\Services;

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioCallSent;
use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
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
     * @param array<string, mixed> $options
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
     * Make a voice call.
     *
     * @param string $to The recipient phone number
     * @param string $url The TwiML URL for the call
     * @param array<string, mixed> $options Additional options for the call
     * @return mixed
     */
    public function makeCall(string $to, string $url, array $options = [])
    {
        // Use the queue if enabled in config
        if (config('twilio-laravel.queue_messages', true)) {
            return $this->queueCall($to, $url, $options);
        }

        return $this->makeCallNow($to, $url, $options);
    }

    /**
     * Send an SMS message immediately.
     *
     * @param array<string, mixed> $options
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

            // Add status callback URL if provided
            if (! empty($options['statusCallback'])) {
                $messageData['statusCallback'] = $options['statusCallback'];
            } elseif (! empty($options['metadata']['statusCallback'])) {
                $messageData['statusCallback'] = $options['metadata']['statusCallback'];
            }

            // Debug logging
            if (config('twilio-laravel.debug', false)) {
                Log::debug('Twilio: Sending message', ['to' => $to, 'options' => $options]);
            }

            // Send the message
            $messageResponse = $this->client->messages->create($to, $messageData);

            // Calculate segments count (for logging/events)
            $segmentsCount = (int) ceil(mb_strlen((string) $messageData['body']) / 153);

            // Fire the sent event
            event(new TwilioMessageSent(
                $messageResponse->sid ?? '',
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
     * Make a voice call immediately.
     *
     * @param string $to The recipient phone number
     * @param string $url The TwiML URL for the call
     * @param array<string, mixed> $options Additional options for the call
     * @return mixed
     */
    public function makeCallNow(string $to, string $url, array $options = [])
    {
        try {
            // Fire the sending event and allow for cancellation
            $sendingEvent = new TwilioCallSending($to, $url, $options);
            event($sendingEvent);

            // Check if the call was cancelled
            if ($sendingEvent->cancelled()) {
                if (config('twilio-laravel.debug', false)) {
                    Log::info('Twilio: Call cancelled', [
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

            $callData = [
                'url' => $url,
                'to' => $to,
            ];

            // Validate and set sender
            if (! empty($options['from'])) {
                $callData['from'] = $options['from'];
            } elseif (! empty(config('twilio-laravel.from'))) {
                $callData['from'] = config('twilio-laravel.from');
            } else {
                throw new \Exception('No valid sender configured for Twilio.');
            }

            // Add status callback URL if provided
            if (! empty($options['statusCallback'])) {
                $callData['statusCallback'] = $options['statusCallback'];
            }

            // Add status callback events if provided
            if (! empty($options['statusCallbackEvent'])) {
                $callData['statusCallbackEvent'] = $options['statusCallbackEvent'];
            }

            // Add recording options if provided
            if (isset($options['record'])) {
                $callData['record'] = $options['record'] ? 'true' : 'false';
            }

            // Add timeout if provided
            if (! empty($options['timeout'])) {
                $callData['timeout'] = $options['timeout'];
            }

            // Debug logging
            if (config('twilio-laravel.debug', false)) {
                Log::debug('Twilio: Making call', ['to' => $to, 'options' => $options]);
            }

            // Make the call
            // Extract 'from' parameter and remove it from callData
            $from = $callData['from'];
            unset($callData['to'], $callData['from']);

            // Call with correct parameter order: to, from, options
            $callResponse = $this->client->calls->create($to, $from, $callData);

            // Fire the sent event
            event(new TwilioCallSent(
                $callResponse->sid ?? '',
                $to,
                $url,
                $callResponse->status,
                $options
            ));

            return [
                'status' => 'initiated',
                'to' => $to,
                'callSid' => $callResponse->sid,
            ];
        } catch (\Exception $e) {
            if (config('twilio-laravel.debug', false)) {
                Log::error('Twilio: Failed to make call', [
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
     * @param array<string, mixed> $options
     * @return array<string, mixed>
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
        $segmentsCount = (int) ceil(mb_strlen($message) / 153);

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

    /**
     * Queue a voice call for sending.
     *
     * @param string $to The recipient phone number
     * @param string $url The TwiML URL for the call
     * @param array<string, mixed> $options Additional options for the call
     * @return array<string, mixed>
     */
    public function queueCall(string $to, string $url, array $options = [])
    {
        // Fire the sending event and allow for cancellation
        $sendingEvent = new TwilioCallSending($to, $url, $options);
        event($sendingEvent);

        // Check if the call was cancelled
        if ($sendingEvent->cancelled()) {
            if (config('twilio-laravel.debug', false)) {
                Log::info('Twilio: Call cancelled', [
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

        // Set queue name and delay
        $queueName = $options['queue'] ?? config('twilio-laravel.queue_name', 'default');
        $delay = $options['delay'] ?? null;

        // Create job
        $job = new SendTwilioCall($to, $url, $options);

        // Queue with optional delay
        if ($delay) {
            $job->delay($delay)->onQueue($queueName);
        } else {
            $job->onQueue($queueName);
        }

        // Dispatch the job
        dispatch($job);

        // Fire the queued event
        event(new TwilioCallQueued(
            $to,
            $url,
            'queued',
            $options
        ));

        return ['status' => 'queued', 'to' => $to];
    }
}
