<?php

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Tests\Support\TransportTwilioService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Twilio\Rest\Api\V2010\Account\MessageInstance;

beforeEach(function () {
    config(['twilio-laravel.from' => '+15550000001', 'twilio-laravel.messaging_service_sid' => null]);
    $this->service = new TransportTwilioService;
});

it('serializes sender precedence through the real SDK', function ($options, $configuredService, $expected) {
    config(['twilio-laravel.messaging_service_sid' => $configuredService]);
    $result = $this->service->sendMessageNow('+15550000002', 'Hello', $options);
    expect($result)->toBeInstanceOf(MessageInstance::class);
    $request = $this->service->transport->requests[0];
    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toEndWith('/Accounts/AC_TEST/Messages.json')
        ->and($request['data'])->toMatchArray(['To' => '+15550000002', 'Body' => 'Hello'] + $expected)
        ->and(array_intersect_key($request['data'], array_flip(['From', 'MessagingServiceSid'])))->toBe($expected);
})->with([
    'explicit from' => [['from' => '+15550000003', 'messagingServiceSid' => 'MG_EXPLICIT'], 'MG_DEFAULT', ['From' => '+15550000003']],
    'explicit service' => [['messagingServiceSid' => 'MG_EXPLICIT'], 'MG_DEFAULT', ['MessagingServiceSid' => 'MG_EXPLICIT']],
    'configured service' => [[], 'MG_DEFAULT', ['MessagingServiceSid' => 'MG_DEFAULT']],
    'configured from' => [[], null, ['From' => '+15550000001']],
]);

it('accepts an explicit messaging service without any configured sender', function () {
    config(['twilio-laravel.from' => null]);
    $this->service->sendMessageNow('+15550000002', 'Hello', ['messagingServiceSid' => 'MG_EXPLICIT']);
    expect($this->service->transport->requests[0]['data']['MessagingServiceSid'])->toBe('MG_EXPLICIT');
});

it('serializes media and callbacks without forwarding application metadata', function ($options, $callback) {
    $this->service->sendMessageNow('+15550000002', 'Hello', $options + ['mediaUrls' => ['https://example.test/a', 'https://example.test/b'], '_notification' => ['id' => 12], 'queue' => 'sms', 'custom' => 'private']);
    $data = $this->service->transport->requests[0]['data'];
    expect($data)->toMatchArray(['MediaUrl' => ['https://example.test/a', 'https://example.test/b'], 'StatusCallback' => $callback])
        ->not->toHaveKeys(['_notification', 'queue', 'custom', 'metadata']);
})->with([
    [['statusCallback' => 'https://example.test/direct', 'metadata' => ['statusCallback' => 'https://example.test/legacy']], 'https://example.test/direct'],
    [['metadata' => ['statusCallback' => 'https://example.test/legacy']], 'https://example.test/legacy'],
]);

it('serializes call options including explicit false recording', function ($record) {
    $this->service->transport->response = ['sid' => 'CA_TEST', 'status' => 'queued'];
    $result = $this->service->makeCallNow('+15550000002', 'https://example.test/twiml', ['record' => $record, 'timeout' => 30, 'statusCallback' => 'https://example.test/status', 'statusCallbackEvent' => ['ringing', 'completed']]);
    expect($result)->toBe(['status' => 'initiated', 'to' => '+15550000002', 'callSid' => 'CA_TEST'])
        ->and($this->service->transport->requests[0]['data'])->toMatchArray(['To' => '+15550000002', 'From' => '+15550000001', 'Url' => 'https://example.test/twiml', 'Record' => $record ? 'true' : 'false', 'Timeout' => 30, 'StatusCallbackEvent' => ['ringing', 'completed'], 'StatusCallback' => 'https://example.test/status']);
})->with([true, false]);

it('prefers reported segments and retains an estimate when unavailable', function ($reported, $expected) {
    Event::fake([TwilioMessageSent::class]);
    $this->service->transport->response['num_segments'] = $reported;
    $this->service->sendMessageNow('+15550000002', str_repeat('a', 154));
    Event::assertDispatched(TwilioMessageSent::class, fn ($event) => $event->segmentsCount === $expected);
})->with([['3', 3], ['0', 2], [null, 2], ['invalid', 2]]);

it('checks cancellation once per enqueue and worker attempt', function ($voice, $cancelAt) {
    Bus::fake();
    $count = 0;
    $eventClass = $voice ? TwilioCallSending::class : TwilioMessageSending::class;
    Event::listen($eventClass, function ($event) use (&$count, $cancelAt) {
        if (++$count === $cancelAt) {
            $event->cancel('private reason');
        }
    });
    $method = $voice ? 'queueCall' : 'queueMessage';
    $this->service->$method('+15550000002', 'content');
    if ($cancelAt === 1) {
        Bus::assertNothingDispatched();
        expect($count)->toBe(1)->and($this->service->transport->requests)->toBe([]);

        return;
    }
    $jobClass = $voice ? SendTwilioCall::class : SendTwilioMessage::class;
    Bus::assertDispatched($jobClass);
    $job = Bus::dispatched($jobClass)->first();
    $job->handle($this->service);
    expect($count)->toBe(2)->and($this->service->transport->requests)->toHaveCount($cancelAt === 2 ? 0 : 1);
})->with([false, true])->with([1, 2, 99]);

it('preserves date based queue delays and falls back from invalid queue names', function ($voice, $delayKind) {
    $delay = $delayKind === 'datetime' ? new DateTimeImmutable('+1 minute') : new DateInterval('PT1M');
    Bus::fake();
    $method = $voice ? 'queueCall' : 'queueMessage';
    $this->service->$method('+15550000002', 'content', ['queue' => [], 'delay' => $delay]);
    $class = $voice ? SendTwilioCall::class : SendTwilioMessage::class;
    Bus::assertDispatched($class, fn ($job) => $job->queue === 'default' && $job->delay === $delay);
})->with([false, true])->with(['datetime', 'interval']);
