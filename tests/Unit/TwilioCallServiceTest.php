<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Events\TwilioCallQueued;
use Citricguy\TwilioLaravel\Events\TwilioCallSent;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Twilio\Rest\Api\V2010\Account\CallInstance;
use Twilio\Rest\Api\V2010\Account\CallList;
use Twilio\Rest\Client;

beforeEach(function () {
    config(['twilio-laravel.account_sid' => 'test_sid']);
    config(['twilio-laravel.auth_token' => 'test_token']);
});

it('queues calls when queuing is enabled', function () {
    config(['twilio-laravel.queue_messages' => true]);
    Queue::fake();
    Event::fake();

    $service = new TwilioService;

    $mockClient = Mockery::mock(Client::class);
    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml');

    Queue::assertPushed(SendTwilioCall::class, function ($job) {
        return $job->to === '+12345678901' && $job->url === 'https://example.com/twiml';
    });

    Event::assertDispatched(TwilioCallQueued::class, function ($event) {
        return $event->to === '+12345678901' && $event->url === 'https://example.com/twiml';
    });
});

it('makes calls immediately when queuing is disabled', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);
    Event::fake();

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+19876543210', Mockery::on(function ($arg) {
            return $arg['url'] === 'https://example.com/twiml';
        }))
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $result = $service->makeCall('+12345678901', 'https://example.com/twiml');

    expect($result['status'])->toBe('initiated');
    expect($result['callSid'])->toBe('CA123456');

    Event::assertDispatched(TwilioCallSent::class, function ($event) {
        return $event->to === '+12345678901' && $event->callSid === 'CA123456';
    });
});

it('allows customizing the from number for calls', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+15551234567', Mockery::any())
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml', [
        'from' => '+15551234567',
    ]);
});

it('throws exception when no sender is configured for calls', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => null]);

    $service = new TwilioService;

    $mockCallList = Mockery::mock(CallList::class);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml');
})->throws(\Exception::class, 'No valid sender configured for Twilio.');

it('includes status callback in call options', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+19876543210', Mockery::on(function ($arg) {
            return $arg['statusCallback'] === 'https://example.com/status';
        }))
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml', [
        'statusCallback' => 'https://example.com/status',
    ]);
});

it('includes recording option in call', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+19876543210', Mockery::on(function ($arg) {
            // Service converts boolean record to string 'true'
            return isset($arg['record']) && $arg['record'] === 'true';
        }))
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml', [
        'record' => true,
    ]);
});

it('includes timeout option in call', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+19876543210', Mockery::on(function ($arg) {
            return isset($arg['timeout']) && $arg['timeout'] === 30;
        }))
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml', [
        'timeout' => 30,
    ]);
});

it('handles call api exceptions and rethrows', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);
    config(['twilio-laravel.debug' => true]);

    $service = new TwilioService;

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->andThrow(new \Exception('API Error: Call failed'));

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml');
})->throws(\Exception::class, 'API Error: Call failed');

it('includes status callback events in call', function () {
    config(['twilio-laravel.queue_messages' => false]);
    config(['twilio-laravel.from' => '+19876543210']);

    $service = new TwilioService;

    $mockCall = Mockery::mock(CallInstance::class);
    $mockCall->sid = 'CA123456';
    $mockCall->status = 'initiated';

    $mockCallList = Mockery::mock(CallList::class);
    $mockCallList->shouldReceive('create')
        ->once()
        ->with('+12345678901', '+19876543210', Mockery::on(function ($arg) {
            return isset($arg['statusCallbackEvent']) && $arg['statusCallbackEvent'] === ['initiated', 'ringing', 'answered', 'completed'];
        }))
        ->andReturn($mockCall);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->calls = $mockCallList;

    $reflectionProperty = new \ReflectionProperty($service, 'client');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($service, $mockClient);

    $service->makeCall('+12345678901', 'https://example.com/twiml', [
        'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
    ]);
});
