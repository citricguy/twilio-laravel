<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Notifications\TwilioCallMessage;

it('can create a call message with a url', function () {
    $message = new TwilioCallMessage('https://example.com/twiml');

    expect($message->url)->toBe('https://example.com/twiml');
    expect($message->options)->toBe([]);
});

it('can set url via fluent method', function () {
    $message = (new TwilioCallMessage)
        ->url('https://example.com/twiml');

    expect($message->url)->toBe('https://example.com/twiml');
});

it('can set from number', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->from('+1234567890');

    expect($message->options['from'])->toBe('+1234567890');
});

it('can set status callback', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->statusCallback('https://example.com/status');

    expect($message->options['statusCallback'])->toBe('https://example.com/status');
});

it('can set status callback events', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->statusCallbackEvent(['initiated', 'ringing', 'answered', 'completed']);

    expect($message->options['statusCallbackEvent'])->toBe(['initiated', 'ringing', 'answered', 'completed']);
});

it('can enable recording', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->record(true);

    expect($message->options['record'])->toBeTrue();
});

it('can disable recording', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->record(false);

    expect($message->options['record'])->toBeFalse();
});

it('can set timeout', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->timeout(30);

    expect($message->options['timeout'])->toBe(30);
});

it('can set additional options', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->options(['customKey' => 'customValue']);

    expect($message->options['customKey'])->toBe('customValue');
});

it('merges options correctly', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->from('+1234567890')
        ->options(['customKey' => 'customValue']);

    expect($message->options['from'])->toBe('+1234567890');
    expect($message->options['customKey'])->toBe('customValue');
});

it('converts to array correctly', function () {
    $message = (new TwilioCallMessage('https://example.com/twiml'))
        ->from('+1234567890')
        ->statusCallback('https://example.com/status');

    $array = $message->toArray();

    expect($array)->toBe([
        'url' => 'https://example.com/twiml',
        'options' => [
            'from' => '+1234567890',
            'statusCallback' => 'https://example.com/status',
        ],
    ]);
});

it('can chain all fluent methods', function () {
    $message = (new TwilioCallMessage)
        ->url('https://example.com/twiml')
        ->from('+1234567890')
        ->statusCallback('https://example.com/status')
        ->statusCallbackEvent(['completed'])
        ->record(true)
        ->timeout(60)
        ->options(['extra' => 'option']);

    expect($message->url)->toBe('https://example.com/twiml');
    expect($message->options['from'])->toBe('+1234567890');
    expect($message->options['statusCallback'])->toBe('https://example.com/status');
    expect($message->options['statusCallbackEvent'])->toBe(['completed']);
    expect($message->options['record'])->toBeTrue();
    expect($message->options['timeout'])->toBe(60);
    expect($message->options['extra'])->toBe('option');
});
