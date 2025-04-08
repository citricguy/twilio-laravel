<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TestUser
{
    use Notifiable;

    public $email;

    public $phone;

    public $id;

    public function __construct($id = 1, $phone = '+15555555555')
    {
        $this->id = $id;
        $this->phone = $phone;
        $this->email = 'test@example.com';
    }

    public function getKey()
    {
        return $this->id;
    }

    public function routeNotificationForTwilioSms()
    {
        return $this->phone;
    }
}

class TestSmsNotification extends Notification
{
    protected $message;

    protected $options;

    public function __construct($message, $options = [])
    {
        $this->message = $message;
        $this->options = $options;
    }

    public function via($notifiable)
    {
        return ['twilioSms'];
    }

    public function toTwilioSms($notifiable)
    {
        if (isset($this->options['simple']) && $this->options['simple']) {
            return $this->message;
        }

        $message = new TwilioSmsMessage($this->message);

        if (isset($this->options['from'])) {
            $message->from($this->options['from']);
        }

        if (isset($this->options['mediaUrls'])) {
            $message->mediaUrls($this->options['mediaUrls']);
        }

        if (isset($this->options['statusCallback'])) {
            $message->statusCallback($this->options['statusCallback']);
        }

        return $message;
    }
}

it('can send an SMS notification via the notification system', function () {
    // Fake the Twilio facade
    Twilio::fake();

    // Setup test user
    $user = new TestUser;

    // Send notification
    $user->notify(new TestSmsNotification('This is a test notification'));

    // Ensure the notification was sent via Twilio
    Twilio::assertSent(function ($message) {
        return $message->body === 'This is a test notification' &&
               $message->to === '+15555555555';
    });
});

it('can send an MMS notification with media', function () {
    Twilio::fake();

    $user = new TestUser;

    // Send MMS notification
    $user->notify(new TestSmsNotification('Check out this image', [
        'mediaUrls' => ['https://example.com/image.jpg'],
    ]));

    // Assert the notification was sent with media URLs
    Twilio::assertSent(function ($message) {
        return $message->body === 'Check out this image' &&
               $message->options['mediaUrls'][0] === 'https://example.com/image.jpg';
    });
});

it('can use a different sender phone number', function () {
    Twilio::fake();

    $user = new TestUser;

    // Send notification with custom from number
    $user->notify(new TestSmsNotification('Message from custom number', [
        'from' => '+16666666666',
    ]));

    // Assert the custom from number was used
    Twilio::assertSent(function ($message) {
        return $message->options['from'] === '+16666666666';
    });
});

it('can be sent with a status callback URL', function () {
    Twilio::fake();

    $user = new TestUser;

    // Send notification with status callback
    $user->notify(new TestSmsNotification('Message with status callback', [
        'statusCallback' => 'https://example.com/webhook/status',
    ]));

    // Assert the status callback URL was set
    Twilio::assertSent(function ($message) {
        return $message->options['statusCallback'] === 'https://example.com/webhook/status';
    });
});

it('includes notification metadata in the event', function () {
    Twilio::fake();

    $user = new TestUser(42);

    // Send a notification
    $notification = new TestSmsNotification('Testing metadata');
    $user->notify($notification);

    // Assert metadata was included
    Twilio::assertSent(function ($message) use ($user, $notification) {
        return isset($message->options['_notification']) &&
               $message->options['_notification']['type'] === get_class($notification) &&
               $message->options['_notification']['notifiable'] === get_class($user) &&
               $message->options['_notification']['notifiable_id'] === 42;
    });
});
