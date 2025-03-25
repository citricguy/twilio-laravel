<?php

namespace Citricguy\TwilioLaravel\Testing;

use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Services\TwilioService;
use PHPUnit\Framework\Assert as PHPUnit;

class TwilioServiceFake extends TwilioService
{
    /**
     * All of the messages that have been sent.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Create a new fake Twilio service instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Don't call parent constructor to avoid creating a real Twilio client
    }

    /**
     * Send an SMS message (fake implementation).
     *
     * @param string $to
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessage(string $to, string $message, array $options = [])
    {
        return $this->recordMessage('queued', $to, $message, $options);
    }

    /**
     * Send an SMS message immediately (fake implementation).
     *
     * @param string $to
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessageNow(string $to, string $message, array $options = [])
    {
        return $this->recordMessage('sent', $to, $message, $options);
    }

    /**
     * Queue an SMS message for sending (fake implementation).
     *
     * @param string $to
     * @param string $message
     * @param array $options
     * @return array
     */
    public function queueMessage(string $to, string $message, array $options = [])
    {
        return $this->recordMessage('queued', $to, $message, $options);
    }

    /**
     * Record a message as sent.
     *
     * @param string $type
     * @param string $to
     * @param string $message
     * @param array $options
     * @return array
     */
    protected function recordMessage($type, $to, $message, $options)
    {
        $this->messages[] = (object) [
            'type' => $type,
            'to' => $to,
            'body' => $message,
            'options' => $options,
            'messageSid' => 'FAKE_SID_' . count($this->messages),
            'status' => $type === 'sent' ? 'sent' : 'queued',
            'segmentsCount' => ceil(mb_strlen($message) / 153),
        ];
        
        // Fire appropriate event
        if ($type === 'queued') {
            event(new TwilioMessageQueued(
                $to,
                $message,
                'queued',
                ceil(mb_strlen($message) / 153),
                $options
            ));
        } else {
            event(new \Citricguy\TwilioLaravel\Events\TwilioMessageSent(
                $this->messages[count($this->messages) - 1]->messageSid,
                $to,
                $message,
                'sent',
                ceil(mb_strlen($message) / 153),
                $options
            ));
        }

        return [
            'status' => $type,
            'to' => $to,
            'messageSid' => $this->messages[count($this->messages) - 1]->messageSid,
        ];
    }

    /**
     * Assert if a message was sent based on a truth-test callback.
     *
     * @param callable|null $callback
     * @return void
     */
    public function assertSent(?callable $callback = null)
    {
        if (count($this->messages) === 0) {
            PHPUnit::fail('No messages have been sent.');
        }

        if ($callback === null) {
            PHPUnit::assertTrue(true);
            return;
        }

        foreach ($this->messages as $message) {
            if ($callback($message)) {
                PHPUnit::assertTrue(true);
                return;
            }
        }

        PHPUnit::fail('No messages were sent that matched the given criteria.');
    }

    /**
     * Assert if a message was sent to the given recipient.
     *
     * @param string $recipient
     * @return void
     */
    public function assertSentTo($recipient)
    {
        return $this->assertSent(function ($message) use ($recipient) {
            return $message->to === $recipient;
        });
    }

    /**
     * Assert that a message was sent a specific number of times.
     *
     * @param int $count
     * @return void
     */
    public function assertSentCount($count)
    {
        PHPUnit::assertCount(
            $count, $this->messages,
            "Expected {$count} messages to have been sent, but " . count($this->messages) . " were sent."
        );
    }

    /**
     * Assert that no messages were sent.
     *
     * @return void
     */
    public function assertNothingSent()
    {
        PHPUnit::assertEmpty($this->messages, 'Messages were sent unexpectedly.');
    }
}
