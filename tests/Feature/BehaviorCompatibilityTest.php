<?php

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Notifications\TwilioCallChannel;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

it('returns the first listener response unchanged including JSON and Symfony responses', function ($kind) {
    config(['twilio-laravel.validate_webhook' => false]);
    $expected = match ($kind) {
        'json' => response()->json(['handled' => true], 200, ['X-Listener' => 'first']),
        'symfony' => new Response('<Response/>', 200, ['Content-Type' => 'text/xml', 'X-Listener' => 'first']),
        'laravel' => response('<Response/>', 200, ['Content-Type' => 'text/xml', 'X-Listener' => 'first']),
    };
    Event::listen(TwilioWebhookReceived::class, fn () => null);
    Event::listen(TwilioWebhookReceived::class, fn () => $expected);
    Event::listen(TwilioWebhookReceived::class, fn () => response('second'));
    $this->postJson(config('twilio-laravel.webhook_path'), ['CallSid' => 'CA_TEST'])->assertStatus(200)->assertHeader('X-Listener', 'first')->assertContent($expected->getContent());
})->with(['json', 'symfony', 'laravel']);

it('recognizes early voice progress callbacks while retaining inbound fallbacks', function ($status) {
    $payload = ['CallSid' => 'CA_TEST', 'CallStatus' => $status];
    $callback = new TwilioWebhookReceived($payload + ['CallbackSource' => 'call-progress-events']);
    $inbound = new TwilioWebhookReceived($payload);
    expect($callback->isVoiceStatusUpdate())->toBeTrue()->and($callback->getStatusType())->toBe($status)->and($inbound->isInboundVoiceCall())->toBeTrue();
})->with(['queued', 'ringing', 'in-progress']);

it('fakes the configured dispatch mode and all cancellation entry points', function ($voice, $queued, $entry, $cancel) {
    config(['twilio-laravel.queue_messages' => $queued]);
    Twilio::fake();
    $calls = 0;
    Event::listen($voice ? TwilioCallSending::class : TwilioMessageSending::class, function ($event) use (&$calls, $cancel) {
        $calls++;
        if ($cancel) {
            $event->cancel('test reason');
        }
    });
    $method = match ($entry) {
        'automatic' => $voice ? 'makeCall' : 'sendMessage', 'now' => $voice ? 'makeCallNow' : 'sendMessageNow', 'queue' => $voice ? 'queueCall' : 'queueMessage'
    };
    $result = Twilio::$method('+15550000002', 'content');
    expect($calls)->toBe(1);
    if ($cancel) {
        expect($result)->toBe(['status' => 'cancelled', 'to' => '+15550000002', 'reason' => 'test reason']);
        $voice ? Twilio::assertNoCalls() : Twilio::assertNothingSent();
    } else {
        $expected = ($entry === 'queue' || ($entry === 'automatic' && $queued)) ? 'queued' : ($voice ? 'initiated' : 'sent');
        expect($result['status'])->toBe($expected);
        $voice ? Twilio::assertCallCount(1) : Twilio::assertSentCount(1);
    }
})->with([false, true])->with([false, true])->with(['automatic', 'now', 'queue'])->with([false, true]);

it('rejects invalid notification return values with a clear error', function ($voice, $value) {
    $notifiable = new class
    {
        public function routeNotificationFor($channel, $notification)
        {
            return '+15550000002';
        }
    };
    $notification = new class($value) extends Notification
    {
        public function __construct(public mixed $value) {}

        public function toTwilioSms($notifiable)
        {
            return $this->value;
        }

        public function toTwilioCall($notifiable)
        {
            return $this->value;
        }
    };
    $channel = $voice ? new TwilioCallChannel : new TwilioSmsChannel;
    expect(fn () => $channel->send($notifiable, $notification))->toThrow(UnexpectedValueException::class);
})->with([false, true])->with([null, false, 123, [['content' => '', 'url' => '']]]);

it('acknowledges unhandled voice progress while returning inbound listener TwiML', function ($progress) {
    config(['twilio-laravel.validate_webhook' => false]);
    Event::listen(TwilioWebhookReceived::class, function ($event) {
        if ($event->isInboundVoiceCall()) {
            return response('<Response><Say>Hello</Say></Response>', 200, ['Content-Type' => 'text/xml']);
        }

    });
    $payload = ['CallSid' => 'CA_TEST', 'CallStatus' => 'ringing'];
    if ($progress) {
        $payload['CallbackSource'] = 'call-progress-events';
    }
    $response = $this->post(config('twilio-laravel.webhook_path'), $payload);
    $response->assertStatus($progress ? 202 : 200);
    if (! $progress) {
        $response->assertContent('<Response><Say>Hello</Say></Response>');
    }
})->with([false, true]);
