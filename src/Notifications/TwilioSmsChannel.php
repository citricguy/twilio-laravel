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
        if (! method_exists($notifiable, 'routeNotificationForTwilioSms')) {
            return;
        }

        $to = $notifiable->routeNotificationForTwilioSms($notification);
        if (! $to) {
            return;
        }

        $message = $notification->toTwilioSms($notifiable);

        if (is_string($message)) {
            $message = ['content' => $message];
        } elseif ($message instanceof TwilioSmsMessage) {
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

        Twilio::sendMessage(
            $to,
            $message['content'],
            $options
        );
    }
}
