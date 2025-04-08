<?php

namespace Citricguy\TwilioLaravel\Notifications;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Illuminate\Notifications\Notification;

class TwilioSmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $to = $notifiable->routeNotificationForTwilioSms($notification)) {
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
        $options['_notification'] = [
            'type' => get_class($notification),
            'notifiable' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey() ?? null,
        ];

        Twilio::sendMessage(
            $to,
            $message['content'],
            $options
        );
    }
}
