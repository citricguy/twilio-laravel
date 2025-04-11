<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioCallSending
{
    use Dispatchable, SerializesModels;

    /**
     * The recipient phone number.
     *
     * @var string
     */
    public $to;

    /**
     * The TwiML URL for the call.
     *
     * @var string
     */
    public $url;

    /**
     * Additional options for the call.
     *
     * @var array
     */
    public $options;

    /**
     * Whether the call has been cancelled.
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
     * @return void
     */
    public function __construct(string $to, string $url, array $options = [])
    {
        $this->to = $to;
        $this->url = $url;
        $this->options = $options;
    }

    /**
     * Cancel the call.
     *
     * @return $this
     */
    public function cancel(?string $reason = null)
    {
        $this->cancelled = true;
        $this->cancellationReason = $reason;

        return $this;
    }

    /**
     * Determine if the call has been cancelled.
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