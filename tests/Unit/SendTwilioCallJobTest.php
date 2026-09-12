<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Citricguy\TwilioLaravel\Tests\Support\TransportTwilioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['twilio-laravel.debug' => false]);
});

it('can be constructed with required parameters', function () {
    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');

    expect($job->to)->toBe('+12345678901');
    expect($job->url)->toBe('https://example.com/twiml');
    expect($job->options)->toBe([]);
});

it('can be constructed with additional options', function () {
    $options = [
        'from' => '+19876543210',
        'timeout' => 30,
        'record' => true,
    ];

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml', $options);

    expect($job->to)->toBe('+12345678901');
    expect($job->url)->toBe('https://example.com/twiml');
    expect($job->options)->toBe($options);
});

it('fires TwilioCallSending event when handled', function () {
    Event::fake([TwilioCallSending::class]);

    $twilioService = new TransportTwilioService;
    config(['twilio-laravel.from' => '+19876543210']);

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');
    $job->handle($twilioService);

    Event::assertDispatched(TwilioCallSending::class, function ($event) {
        return $event->to === '+12345678901' && $event->url === 'https://example.com/twiml';
    });
});

it('calls makeCallNow on TwilioService when handled', function () {
    $options = ['timeout' => 30];

    $twilioService = mock(TwilioService::class);
    $twilioService->expects('makeCallNow')
        ->once()
        ->with('+12345678901', 'https://example.com/twiml', $options);

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml', $options);
    $job->handle($twilioService);
});

it('does not make call when sending event is cancelled', function () {
    Event::listen(TwilioCallSending::class, function (TwilioCallSending $event) {
        $event->cancel('Test cancellation');
    });

    $twilioService = new TransportTwilioService;

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');
    $job->handle($twilioService);
    expect($twilioService->transport->requests)->toBe([]);
});

it('logs cancellation when debug mode is enabled', function () {
    config(['twilio-laravel.debug' => true]);

    Event::listen(TwilioCallSending::class, function (TwilioCallSending $event) {
        $event->cancel('Test cancellation reason');
    });

    Log::shouldReceive('info')
        ->once()
        ->with('Twilio: Call cancelled', ['has_reason' => true]);

    $twilioService = new TransportTwilioService;

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');
    $job->handle($twilioService);
    expect($twilioService->transport->requests)->toBe([]);
});

it('does not log cancellation when debug mode is disabled', function () {
    config(['twilio-laravel.debug' => false]);

    Event::listen(TwilioCallSending::class, function (TwilioCallSending $event) {
        $event->cancel('Test cancellation reason');
    });

    Log::shouldReceive('info')->never();

    $twilioService = new TransportTwilioService;

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');
    $job->handle($twilioService);
    expect($twilioService->transport->requests)->toBe([]);
});

it('passes options through to the service', function () {
    $options = [
        'from' => '+19876543210',
        'statusCallback' => 'https://example.com/status',
        'statusCallbackEvent' => ['initiated', 'ringing', 'completed'],
        'timeout' => 60,
        'record' => true,
    ];

    $twilioService = mock(TwilioService::class);
    $twilioService->expects('makeCallNow')
        ->once()
        ->with('+12345678901', 'https://example.com/twiml', $options);

    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml', $options);
    $job->handle($twilioService);
});

it('implements ShouldQueue interface', function () {
    $job = new SendTwilioCall('+12345678901', 'https://example.com/twiml');

    expect($job)->toBeInstanceOf(ShouldQueue::class);
});
