<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

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
     * @param array $payload
     * @param string|null $type
     * @return void
     */
    public function __construct(array $payload, ?string $type = null)
    {
        $this->payload = $payload;
        $this->type = $type ?: $this->determineType($payload);
    }

    /**
     * Attempt to determine the webhook type from payload.
     *
     * @param array $payload
     * @return string|null
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