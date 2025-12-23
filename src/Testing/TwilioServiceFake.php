<?php

namespace Citricguy\TwilioLaravel\Testing;

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioCallSent;
use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Services\TwilioService;
use PHPUnit\Framework\Assert as PHPUnit;

class TwilioServiceFake extends TwilioService
{
    /**
     * All of the messages that have been sent.
     *
     * @var array<int, object>
     */
    protected $messages = [];

    /**
     * All of the calls that have been made.
     *
     * @var array<int, object>
     */
    protected $calls = [];

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
     * @param array<string, mixed> $options
     * @return array<string, mixed>|false
     */
    public function sendMessage(string $to, string $message, array $options = [])
    {
        // Fire TwilioMessageSending event (for listeners/cancellation)
        $event = new TwilioMessageSending($to, $message, $options);
        event($event);
        if ($event->cancelled()) {
            return false;
        }

        return $this->recordMessage('queued', $to, $message, $options);
    }

    /**
     * Send an SMS message immediately (fake implementation).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendMessageNow(string $to, string $message, array $options = [])
    {
        return $this->recordMessage('sent', $to, $message, $options);
    }

    /**
     * Queue an SMS message for sending (fake implementation).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function queueMessage(string $to, string $message, array $options = [])
    {
        return $this->recordMessage('queued', $to, $message, $options);
    }

    /**
     * Make a voice call (fake implementation).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>|false
     */
    public function makeCall(string $to, string $url, array $options = [])
    {
        // Fire TwilioCallSending event (for listeners/cancellation)
        $event = new TwilioCallSending($to, $url, $options);
        event($event);
        if ($event->cancelled()) {
            return false;
        }

        return $this->recordCall('queued', $to, $url, $options);
    }

    /**
     * Make a voice call immediately (fake implementation).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function makeCallNow(string $to, string $url, array $options = [])
    {
        return $this->recordCall('initiated', $to, $url, $options);
    }

    /**
     * Queue a voice call for sending (fake implementation).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function queueCall(string $to, string $url, array $options = [])
    {
        return $this->recordCall('queued', $to, $url, $options);
    }

    /**
     * Record a message as sent.
     *
     * @param string $type
     * @param string $to
     * @param string $message
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function recordMessage($type, $to, $message, $options)
    {
        $messageSid = 'FAKE_SID_'.count($this->messages);
        $segmentsCount = (int) ceil(mb_strlen($message) / 153);

        $this->messages[] = (object) [
            'type' => $type,
            'to' => $to,
            'body' => $message,
            'options' => $options,
            'messageSid' => $messageSid,
            'status' => $type === 'sent' ? 'sent' : 'queued',
            'segmentsCount' => $segmentsCount,
        ];

        // Fire appropriate event
        if ($type === 'queued') {
            event(new TwilioMessageQueued(
                $to,
                $message,
                'queued',
                $segmentsCount,
                $options
            ));
        } else {
            event(new \Citricguy\TwilioLaravel\Events\TwilioMessageSent(
                $messageSid,
                $to,
                $message,
                'sent',
                $segmentsCount,
                $options
            ));
        }

        return [
            'status' => $type,
            'to' => $to,
            'messageSid' => $messageSid,
        ];
    }

    /**
     * Record a call as initiated.
     *
     * @param string $type
     * @param string $to
     * @param string $url
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function recordCall($type, $to, $url, $options)
    {
        $callSid = 'FAKE_CALL_SID_'.count($this->calls);

        $this->calls[] = (object) [
            'type' => $type,
            'to' => $to,
            'url' => $url,
            'options' => $options,
            'callSid' => $callSid,
            'status' => $type === 'initiated' ? 'initiated' : 'queued',
        ];

        // Fire appropriate event
        if ($type === 'queued') {
            event(new TwilioCallQueued(
                $to,
                $url,
                'queued',
                $options
            ));
        } else {
            event(new TwilioCallSent(
                $callSid,
                $to,
                $url,
                'initiated',
                $options
            ));
        }

        return [
            'status' => $type,
            'to' => $to,
            'callSid' => $callSid,
        ];
    }

    /**
     * Assert if a message was sent based on a truth-test callback.
     *
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
        $this->assertSent(function ($message) use ($recipient) {
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
        $actualCount = count($this->messages);
        PHPUnit::assertSame(
            $count,
            $actualCount,
            "Expected {$count} messages to have been sent, but {$actualCount} were actually sent."
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

    /**
     * Assert if a call was made based on a truth-test callback.
     *
     * @return void
     */
    public function assertCallMade(?callable $callback = null)
    {
        if (count($this->calls) === 0) {
            PHPUnit::fail('No calls have been made.');
        }

        if ($callback === null) {
            PHPUnit::assertTrue(true);

            return;
        }

        foreach ($this->calls as $call) {
            if ($callback($call)) {
                PHPUnit::assertTrue(true);

                return;
            }
        }

        PHPUnit::fail('No calls were made that matched the given criteria.');
    }

    /**
     * Assert if a call was made to the given recipient.
     *
     * @param string $recipient
     * @return void
     */
    public function assertCalledTo($recipient)
    {
        $this->assertCallMade(function ($call) use ($recipient) {
            return $call->to === $recipient;
        });
    }

    /**
     * Assert that a call was made a specific number of times.
     *
     * @param int $count
     * @return void
     */
    public function assertCallCount($count)
    {
        $actualCount = count($this->calls);
        PHPUnit::assertSame(
            $count,
            $actualCount,
            "Expected {$count} calls to have been made, but {$actualCount} were actually made."
        );
    }

    /**
     * Assert that no calls were made.
     *
     * @return void
     */
    public function assertNoCalls()
    {
        PHPUnit::assertEmpty($this->calls, 'Calls were made unexpectedly.');
    }
}
