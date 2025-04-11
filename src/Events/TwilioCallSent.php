<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioCallSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The Twilio call SID.
     *
     * @var string
     */
    public $callSid;

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
     * Call status.
     *
     * @var string
     */
    public $status;

    /**
     * Additional options used for the call.
     *
     * @var array
     */
    public $options;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        string $callSid,
        string $to,
        string $url,
        string $status,
        array $options = []
    ) {
        $this->callSid = $callSid;
        $this->to = $to;
        $this->url = $url;
        $this->status = $status;
        $this->options = $options;
    }
}