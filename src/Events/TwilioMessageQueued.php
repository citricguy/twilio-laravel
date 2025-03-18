<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioMessageQueued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
     * Message status.
     *
     * @var string
     */
    public $status;
    
    /**
     * The number of message segments.
     *
     * @var int
     */
    public $segmentsCount;

    /**
     * Additional options used for the message.
     *
     * @var array
     */
    public $options;

    /**
     * Create a new event instance.
     *
     * @param string $to
     * @param string $message
     * @param string $status
     * @param int $segmentsCount
     * @param array $options
     * @return void
     */
    public function __construct(
        string $to,
        string $message,
        string $status,
        int $segmentsCount,
        array $options = []
    ) {
        $this->to = $to;
        $this->message = $message;
        $this->status = $status;
        $this->segmentsCount = $segmentsCount;
        $this->options = $options;
    }
}
