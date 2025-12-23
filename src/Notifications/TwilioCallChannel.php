<?php

namespace Citricguy\TwilioLaravel\Notifications;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Illuminate\Notifications\Notification;

class TwilioCallChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $to = $notifiable->routeNotificationForTwilioCall($notification)) {
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
        $options['_notification'] = [
            'type' => get_class($notification),
            'notifiable' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey() ?? null,
        ];

        Twilio::makeCall(
            $to,
            $message['url'],
            $options
        );
    }
}
