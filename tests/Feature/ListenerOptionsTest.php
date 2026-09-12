<?php

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioCallSent;
use Citricguy\TwilioLaravel\Events\TwilioMessageQueued;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Tests\Support\TransportTwilioService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

it('propagates synchronous listener options through real SDK requests and serialized workers', function ($voice, $queued) {
    config(['twilio-laravel.from' => '+15550000001']);
    Bus::fake();
    $service = new TransportTwilioService;
    $sending = $voice ? TwilioCallSending::class : TwilioMessageSending::class;
    $sent = $voice ? TwilioCallSent::class : TwilioMessageSent::class;
    $queuedEvent = $voice ? TwilioCallQueued::class : TwilioMessageQueued::class;
    Event::fake([$sent, $queuedEvent]);
    $count = 0;
    Event::listen($sending, function ($event) use (&$count) {
        $count++;
        expect($event->options['metadata']['original'])->toBe('retained');
        if ($count === 2) {
            expect($event->options['statusCallback'])->toBe('https://example.test/status?attempt=1');
        }
        $event->options['statusCallback'] = 'https://example.test/status?attempt='.$count;
        $event->options['metadata']['listener'] = $count;
        $event->options['queue'] = 'compliance';
        $event->options['delay'] = 60;
        unset($event->options['remove']);
    });
    $method = $queued ? ($voice ? 'queueCall' : 'queueMessage') : ($voice ? 'makeCallNow' : 'sendMessageNow');
    $service->$method('+15550000002', 'https://example.test/content', ['metadata' => ['original' => 'retained'], 'remove' => true]);
    if ($queued) {
        $jobClass = $voice ? SendTwilioCall::class : SendTwilioMessage::class;
        $job = unserialize(serialize(Bus::dispatched($jobClass)->first()));
        expect($job->queue)->toBe('compliance')->and($job->delay)->toBe(60)
            ->and($job->options['statusCallback'])->toBe('https://example.test/status?attempt=1');
        Event::assertDispatched($queuedEvent, fn ($event) => $event->options['metadata']['listener'] === 1 && ! isset($event->options['remove']));
        $job->handle($service);
    }
    $expectedCount = $queued ? 2 : 1;
    expect($count)->toBe($expectedCount)->and($service->transport->requests)->toHaveCount(1)
        ->and($service->transport->requests[0]['data']['StatusCallback'])->toBe('https://example.test/status?attempt='.$expectedCount)
        ->and($service->transport->requests[0]['data'])->not->toHaveKey('metadata');
    Event::assertDispatched($sent, fn ($event) => $event->options['metadata'] === ['original' => 'retained', 'listener' => $expectedCount] && $event->options['statusCallback'] === 'https://example.test/status?attempt='.$expectedCount && ! isset($event->options['remove']));
})->with([false, true])->with([false, true]);

it('preserves listener options in standard fake records and events without duplicate dispatch', function ($voice, $queued) {
    config(['twilio-laravel.queue_messages' => $queued]);
    Twilio::fake();
    $sending = $voice ? TwilioCallSending::class : TwilioMessageSending::class;
    $resultEvent = $queued ? ($voice ? TwilioCallQueued::class : TwilioMessageQueued::class) : ($voice ? TwilioCallSent::class : TwilioMessageSent::class);
    Event::fake([$resultEvent]);
    $count = 0;
    Event::listen($sending, function ($event) use (&$count) {
        $count++;
        $event->options['statusCallback'] = 'https://example.test/compliance';
        $event->options['metadata']['listener'] = true;
    });
    $method = $voice ? 'makeCall' : 'sendMessage';
    Twilio::$method('+15550000002', 'content', ['metadata' => ['original' => true]]);
    $matches = fn ($record) => $record->options === ['metadata' => ['original' => true, 'listener' => true], 'statusCallback' => 'https://example.test/compliance'];
    $voice ? Twilio::assertCallMade($matches) : Twilio::assertSent($matches);
    Event::assertDispatched($resultEvent, $matches);
    expect($count)->toBe(1);
})->with([false, true])->with([false, true]);
