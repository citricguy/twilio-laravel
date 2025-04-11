<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Notifications\TwilioCallMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification as NotificationFacade;

beforeEach(function() {
    // Set test credentials to prevent client creation error
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);
});

// Test User Model
class TestCallUser
{
    use Notifiable;

    public $phone;
    public $id;

    public function __construct($id = 1)
    {
        $this->phone = '+15555555555';
        $this->id = $id;
    }

    public function getKey()
    {
        return $this->id;
    }

    public function routeNotificationForTwilioCall($notification)
    {
        return $this->phone;
    }
}

// Test Notification
class TestCallNotification extends Notification
{
    public $options;
    public $url;

    public function __construct($url, array $options = [])
    {
        $this->url = $url;
        $this->options = $options;
    }

    public function via($notifiable)
    {
        return ['twilioCall'];
    }

    public function toTwilioCall($notifiable)
    {
        if (isset($this->options['simple']) && $this->options['simple']) {
            return $this->url;
        }

        $call = new TwilioCallMessage($this->url);

        if (isset($this->options['from'])) {
            $call->from($this->options['from']);
        }

        if (isset($this->options['statusCallback'])) {
            $call->statusCallback($this->options['statusCallback']);
        }

        if (isset($this->options['record'])) {
            $call->record($this->options['record']);
        }

        return $call;
    }
}

it('can send a call notification via the notification system', function () {
    // Fake the Twilio facade
    Twilio::fake();

    // Setup test user
    $user = new TestCallUser();

    // Send notification
    $user->notify(new TestCallNotification('https://example.com/twiml'));

    // Ensure the notification was sent via Twilio
    Twilio::assertCallMade(function ($call) {
        return $call->url === 'https://example.com/twiml' &&
               $call->to === '+15555555555';
    });
});

it('can send a notification with a custom from number', function () {
    Twilio::fake();

    $user = new TestCallUser();

    // Send notification with custom from number
    $user->notify(new TestCallNotification('https://example.com/twiml', [
        'from' => '+16666666666',
    ]));

    // Assert the custom from number was used
    Twilio::assertCallMade(function ($call) {
        return $call->options['from'] === '+16666666666';
    });
});

it('can be sent with a status callback URL', function () {
    Twilio::fake();

    $user = new TestCallUser();

    // Send notification with status callback
    $user->notify(new TestCallNotification('https://example.com/twiml', [
        'statusCallback' => 'https://example.com/webhook/call-status',
    ]));

    // Assert the status callback URL was set
    Twilio::assertCallMade(function ($call) {
        return $call->options['statusCallback'] === 'https://example.com/webhook/call-status';
    });
});

it('can handle simple string URLs', function () {
    Twilio::fake();

    $user = new TestCallUser();

    // Send notification with simple URL string
    $user->notify(new TestCallNotification('https://example.com/twiml-simple', [
        'simple' => true,
    ]));

    // Assert the notification was sent properly
    Twilio::assertCallMade(function ($call) {
        return $call->url === 'https://example.com/twiml-simple';
    });
});

it('includes notification metadata in the call', function () {
    Twilio::fake();

    $user = new TestCallUser(42);

    // Send a notification
    $notification = new TestCallNotification('https://example.com/twiml-metadata');
    $user->notify($notification);

    // Assert notification metadata was included
    Twilio::assertCallMade(function ($call) use ($notification) {
        return isset($call->options['_notification']) &&
               $call->options['_notification']['type'] === get_class($notification) &&
               $call->options['_notification']['notifiable'] === TestCallUser::class &&
               $call->options['_notification']['notifiable_id'] === 42;
    });
});

it('can enable call recording', function () {
    Twilio::fake();

    $user = new TestCallUser();

    // Send notification with recording enabled
    $user->notify(new TestCallNotification('https://example.com/twiml', [
        'record' => true,
    ]));

    // Assert recording was enabled
    Twilio::assertCallMade(function ($call) {
        return isset($call->options['record']) && $call->options['record'] === true;
    });
});