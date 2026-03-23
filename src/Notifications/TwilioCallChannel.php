<?php

namespace Citricguy\TwilioLaravel\Notifications;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Illuminate\Notifications\Notification;

class TwilioCallChannel
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

        $message = $notification->toTwilioCall($notifiable);

        if (is_string($message)) {
            $message = ['url' => $message];
        } elseif ($message instanceof TwilioCallMessage) {
            $message = $message->toArray();
        } elseif (! is_array($message)) {
            throw new \UnexpectedValueException('Twilio call notifications must return a string, array, or TwilioCallMessage instance.');
        }

        $url = $message['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new \UnexpectedValueException('Twilio call notifications must include a non-empty "url" value.');
        }

        $options = is_array($message['options'] ?? null) ? $message['options'] : [];

        // Add notification context to the options
        $notifiableId = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;
        $options['_notification'] = [
            'type' => get_class($notification),
            'notifiable' => get_class($notifiable),
            'notifiable_id' => $notifiableId,
        ];

        Twilio::makeCall(
            $to,
            $url,
            $options
        );
    }

    private function resolveRecipient(object $notifiable, Notification $notification): ?string
    {
        $route = null;

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('twilioCall', $notification);
        } elseif (method_exists($notifiable, 'routeNotificationForTwilioCall')) {
            $route = $notifiable->routeNotificationForTwilioCall($notification);
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
