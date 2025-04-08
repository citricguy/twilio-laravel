<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsChannel;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TestNotifiable
{
    use Notifiable;

    public $phone = '+15555555555';

    public $id = 1;

    public function routeNotificationForTwilioSms()
    {
        return $this->phone;
    }

    public function getKey()
    {
        return $this->id;
    }
}

class TestNotification extends Notification
{
    public function toTwilioSms($notifiable)
    {
        return new TwilioSmsMessage('Test notification message');
    }
}

class TestNotificationWithOptions extends Notification
{
    public function toTwilioSms($notifiable)
    {
        return (new TwilioSmsMessage('Test notification with options'))
            ->from('+16666666666')
            ->mediaUrls(['https://example.com/image.jpg']);
    }
}

class TestNotificationWithString extends Notification
{
    public function toTwilioSms($notifiable)
    {
        return 'Simple string message';
    }
}

it('can send a basic notification', function () {
    Twilio::fake();

    $notifiable = new TestNotifiable;
    $notification = new TestNotification;

    $channel = new TwilioSmsChannel;
    $channel->send($notifiable, $notification);

    Twilio::assertSent(fn ($message) => $message->to === '+15555555555' &&
        $message->body === 'Test notification message' &&
        isset($message->options['_notification']));

    Twilio::assertSent(fn ($message) => $message->options['_notification']['type'] === get_class($notification) &&
        $message->options['_notification']['notifiable'] === get_class($notifiable));
});

it('can send a notification with options', function () {
    Twilio::fake();

    $notifiable = new TestNotifiable;
    $notification = new TestNotificationWithOptions;

    $channel = new TwilioSmsChannel;
    $channel->send($notifiable, $notification);

    Twilio::assertSent(fn ($message) => $message->to === '+15555555555' &&
        $message->body === 'Test notification with options' &&
        $message->options['from'] === '+16666666666' &&
        $message->options['mediaUrls'][0] === 'https://example.com/image.jpg');
});

it('can handle string messages', function () {
    Twilio::fake();

    $notifiable = new TestNotifiable;
    $notification = new TestNotificationWithString;

    $channel = new TwilioSmsChannel;
    $channel->send($notifiable, $notification);

    Twilio::assertSent(fn ($message) => $message->to === '+15555555555' &&
        $message->body === 'Simple string message');
});

it('doesnt send if no phone number is available', function () {
    Twilio::fake();

    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForTwilioSms()
        {
            return null;
        }
    };

    $notification = new TestNotification;

    $channel = new TwilioSmsChannel;
    $channel->send($notifiable, $notification);

    Twilio::assertNothingSent();
});
