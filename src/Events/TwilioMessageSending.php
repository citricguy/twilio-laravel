<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioMessageSending
{
    use Dispatchable, SerializesModels;

    /**
     * The recipient phone number.
     *
     * @var string
     */
    public $to;

    /**
     * The message content.
     *
     * @var string
     */
    public $message;

    /**
     * Additional options for the message.
     *
     * @var array
     */
    public $options;

    /**
     * Whether the message has been cancelled.
     *
     * @var bool
     */
    protected $cancelled = false;

    /**
     * The reason for cancellation, if any.
     *
     * @var string|null
     */
    protected $cancellationReason = null;

    /**
     * Create a new event instance.
     *
     * @param  string  $to
     * @param  string  $message
     * @param  array   $options
     * @return void
     */
    public function __construct(string $to, string $message, array $options = [])
    {
        $this->to = $to;
        $this->message = $message;
        $this->options = $options;
    }

    /**
     * Cancel the message.
     *
     * @param  string|null  $reason
     * @return $this
     */
    public function cancel(?string $reason = null)
    {
        $this->cancelled = true;
        $this->cancellationReason = $reason;

        return $this;
    }

    /**
     * Determine if the message has been cancelled.
     *
     * @return bool
     */
    public function cancelled()
    {
        return $this->cancelled;
    }

    /**
     * Get the cancellation reason.
     *
     * @return string|null
     */
    public function cancellationReason()
    {
        return $this->cancellationReason;
    }
}
