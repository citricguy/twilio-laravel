<?php

namespace Citricguy\TwilioLaravel\Notifications;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Illuminate\Notifications\Notification;

class TwilioSmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $to = $this->resolveRecipient($notifiable, $notification);
        if ($to === null) {
            return;
        }

        $message = $notification->toTwilioSms($notifiable);

        if (is_string($message)) {
            $message = ['content' => $message];
        } elseif ($message instanceof TwilioSmsMessage) {
            $message = $message->toArray();
        } elseif (! is_array($message)) {
            throw new \UnexpectedValueException('Twilio SMS notifications must return a string, array, or TwilioSmsMessage instance.');
        }

        $content = $message['content'] ?? null;
        if (! is_string($content) || $content === '') {
            throw new \UnexpectedValueException('Twilio SMS notifications must include a non-empty "content" value.');
        }

        $options = is_array($message['options'] ?? null) ? $message['options'] : [];

        // Add notification context to the options
        $notifiableId = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;
        $options['_notification'] = [
            'type' => get_class($notification),
            'notifiable' => get_class($notifiable),
            'notifiable_id' => $notifiableId,
        ];

        Twilio::sendMessage(
            $to,
            $content,
            $options
        );
    }

    private function resolveRecipient(object $notifiable, Notification $notification): ?string
    {
        $route = null;

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('twilioSms', $notification);
        } elseif (method_exists($notifiable, 'routeNotificationForTwilioSms')) {
            $route = $notifiable->routeNotificationForTwilioSms($notification);
        }

        return $this->normalizeRecipient($route);
    }

    private function normalizeRecipient(mixed $route): ?string
    {
        if (is_string($route)) {
            $resolved = trim($route);

            return $resolved !== '' ? $resolved : null;
        }

        if (is_object($route) && method_exists($route, '__toString')) {
            $resolved = trim((string) $route);

            return $resolved !== '' ? $resolved : null;
        }

        return null;
    }
}
