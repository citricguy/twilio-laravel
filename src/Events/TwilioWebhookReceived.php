<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwilioWebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Webhook type constants
     */
    public const TYPE_VOICE_STATUS = 'voice-status';

    public const TYPE_VOICE_INBOUND = 'voice-inbound';

    public const TYPE_MESSAGE_STATUS_PREFIX = 'message-status-';

    public const TYPE_MESSAGE_INBOUND_SMS = 'message-inbound-sms';

    public const TYPE_MESSAGE_INBOUND_MMS = 'message-inbound-mms';

    public const TYPE_MESSAGE_GENERIC = 'message';

    /**
     * The webhook payload.
     *
     * @var array<string, mixed>
     */
    public $payload;

    /**
     * The webhook type (SMS, voice, etc).
     *
     * Possible values:
     * - voice-status: Status update for a voice call
     * - voice-inbound: Inbound call or call in progress
     * - message-status-{status}: Status update for message (e.g., message-status-delivered)
     * - message-inbound-sms: Inbound SMS message
     * - message-inbound-mms: Inbound MMS message with media
     * - message: Generic message webhook
     * - null: Unknown webhook type
     *
     * @var string|null
     */
    public $type;

    /**
     * Create a new event instance.
     *
     * @param array<string, mixed> $payload
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
     * @param array<string, mixed> $payload The webhook payload
     * @return string|null The determined webhook type or null if unknown
     */
    protected function determineType(array $payload): ?string
    {
        // Voice calls
        if (isset($payload['CallSid'])) {
            if (isset($payload['CallStatus'])) {
                $status = strtolower($payload['CallStatus']);

                // Inbound call statuses typically include: 'ringing', 'in-progress', 'queued'
                // Status updates typically include: 'completed', 'busy', 'failed', 'no-answer', 'canceled'
                if (in_array($status, ['queued', 'ringing', 'in-progress'])) {
                    return self::TYPE_VOICE_INBOUND;
                }

                return self::TYPE_VOICE_STATUS;
            }

            return self::TYPE_VOICE_INBOUND;
        }

        // SMS/MMS messages
        if (isset($payload['MessageSid'])) {
            // Status webhooks
            if (isset($payload['MessageStatus'])) {
                return self::TYPE_MESSAGE_STATUS_PREFIX.strtolower($payload['MessageStatus']);
            }

            // Inbound SMS vs MMS distinction
            if (isset($payload['Body'])) {
                if (isset($payload['NumMedia']) && intval($payload['NumMedia']) > 0) {
                    return self::TYPE_MESSAGE_INBOUND_MMS;
                }

                return self::TYPE_MESSAGE_INBOUND_SMS;
            }

            return self::TYPE_MESSAGE_GENERIC;
        }

        return null;
    }

    /**
     * Check if this webhook is for an inbound message (SMS or MMS).
     *
     * @return bool True if this is an inbound message
     */
    public function isInboundMessage(): bool
    {
        return $this->type === self::TYPE_MESSAGE_INBOUND_SMS || $this->type === self::TYPE_MESSAGE_INBOUND_MMS;
    }

    /**
     * Check if this webhook is a status update for a message.
     *
     * @return bool True if this is a message status update
     */
    public function isMessageStatusUpdate(): bool
    {
        return $this->type !== null && strpos($this->type, self::TYPE_MESSAGE_STATUS_PREFIX) === 0;
    }

    /**
     * Check if this webhook is for a voice call status update.
     *
     * @return bool True if this is a voice status update
     */
    public function isVoiceStatusUpdate(): bool
    {
        return $this->type === self::TYPE_VOICE_STATUS;
    }

    /**
     * Check if this webhook is any kind of status update.
     *
     * @return bool True if this is any status update
     */
    public function isStatusUpdate(): bool
    {
        return $this->isMessageStatusUpdate() || $this->isVoiceStatusUpdate();
    }

    /**
     * Get the specific status value from the payload.
     *
     * @return string|null The status value (normalized to lowercase) or null if not available
     */
    public function getStatusType(): ?string
    {
        if ($this->isMessageStatusUpdate() && isset($this->payload['MessageStatus'])) {
            return strtolower($this->payload['MessageStatus']);
        }

        if ($this->isVoiceStatusUpdate() && isset($this->payload['CallStatus'])) {
            return strtolower($this->payload['CallStatus']);
        }

        return null;
    }

    /**
     * Check if this webhook is an inbound SMS.
     *
     * @return bool True if this is an inbound SMS
     */
    public function isInboundSms(): bool
    {
        return $this->type === self::TYPE_MESSAGE_INBOUND_SMS;
    }

    /**
     * Check if this webhook is an inbound MMS.
     *
     * @return bool True if this is an inbound MMS
     */
    public function isInboundMms(): bool
    {
        return $this->type === self::TYPE_MESSAGE_INBOUND_MMS;
    }

    /**
     * Check if this webhook is an inbound voice call.
     *
     * @return bool True if this is an inbound voice call
     */
    public function isInboundVoiceCall(): bool
    {
        return $this->type === self::TYPE_VOICE_INBOUND;
    }

    /**
     * Check if this webhook is any kind of voice-related webhook.
     *
     * @return bool True if this is a voice webhook
     */
    public function isVoiceWebhook(): bool
    {
        return in_array($this->type, [
            self::TYPE_VOICE_INBOUND,
            self::TYPE_VOICE_STATUS,
        ]);
    }

    /**
     * Check if this webhook is any kind of message-related webhook.
     *
     * @return bool True if this is a message webhook
     */
    public function isMessageWebhook(): bool
    {
        return $this->isInboundMessage() ||
               $this->isMessageStatusUpdate() ||
               $this->type === self::TYPE_MESSAGE_GENERIC;
    }
}
