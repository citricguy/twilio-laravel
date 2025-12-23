<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioCallQueued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
     * @var array<string, mixed>
     */
    public $options;

    /**
     * Create a new event instance.
     *
     * @param array<string, mixed> $options
     * @return void
     */
    public function __construct(
        string $to,
        string $url,
        string $status,
        array $options = []
    ) {
        $this->to = $to;
        $this->url = $url;
        $this->status = $status;
        $this->options = $options;
    }
}
