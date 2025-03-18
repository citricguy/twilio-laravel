<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioWebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The webhook payload.
     *
     * @var array
     */
    public $payload;

    /**
     * The webhook type (SMS, voice, etc).
     *
     * @var string|null
     */
    public $type;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $payload, ?string $type = null)
    {
        $this->payload = $payload;
        $this->type = $type ?: $this->determineType($payload);
    }

    /**
     * Attempt to determine the webhook type from payload.
     */
    protected function determineType(array $payload): ?string
    {
        if (isset($payload['MessageSid'])) {
            return 'message';
        }

        if (isset($payload['CallSid'])) {
            return 'voice';
        }

        return null;
    }
}
