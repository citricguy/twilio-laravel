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
        if (! method_exists($notifiable, 'routeNotificationForTwilioCall')) {
            return;
        }

        $to = $notifiable->routeNotificationForTwilioCall($notification);
        if (! $to) {
            return;
        }

        $message = $notification->toTwilioCall($notifiable);

        if (is_string($message)) {
            $message = ['url' => $message];
        } elseif ($message instanceof TwilioCallMessage) {
            $message = $message->toArray();
        }

        $options = $message['options'] ?? [];

        // Add notification context to the options
        $notifiableId = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;
        $options['_notification'] = [
            'type' => get_class($notification),
            'notifiable' => get_class($notifiable),
            'notifiable_id' => $notifiableId,
        ];

        Twilio::makeCall(
            $to,
            $message['url'],
            $options
        );
    }
}
