<?php

namespace Citricguy\TwilioLaravel\Tests\Unit;

use Citricguy\TwilioLaravel\Notifications\TwilioSmsMessage;

it('can create an sms message with content', function () {
    $message = new TwilioSmsMessage('Hello World');

    expect($message->content)->toBe('Hello World');
    expect($message->options)->toBe([]);
});

it('can set content via fluent method', function () {
    $message = (new TwilioSmsMessage)
        ->content('Hello World');

    expect($message->content)->toBe('Hello World');
});

it('can set from number', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->from('+1234567890');

    expect($message->options['from'])->toBe('+1234567890');
});

it('can set messaging service sid', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->messagingService('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

    expect($message->options['messagingServiceSid'])->toBe('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
});

it('can set media urls as array', function () {
    $urls = ['https://example.com/image1.jpg', 'https://example.com/image2.jpg'];
    $message = (new TwilioSmsMessage('Hello'))
        ->mediaUrls($urls);

    expect($message->options['mediaUrls'])->toBe($urls);
});

it('can set media urls as single string', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->mediaUrls('https://example.com/image.jpg');

    expect($message->options['mediaUrls'])->toBe(['https://example.com/image.jpg']);
});

it('can set status callback', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->statusCallback('https://example.com/status');

    expect($message->options['statusCallback'])->toBe('https://example.com/status');
});

it('can set additional options', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->options(['customKey' => 'customValue']);

    expect($message->options['customKey'])->toBe('customValue');
});

it('merges options correctly', function () {
    $message = (new TwilioSmsMessage('Hello'))
        ->from('+1234567890')
        ->options(['customKey' => 'customValue']);

    expect($message->options['from'])->toBe('+1234567890');
    expect($message->options['customKey'])->toBe('customValue');
});

it('converts to array correctly', function () {
    $message = (new TwilioSmsMessage('Hello World'))
        ->from('+1234567890')
        ->statusCallback('https://example.com/status');

    $array = $message->toArray();

    expect($array)->toBe([
        'content' => 'Hello World',
        'options' => [
            'from' => '+1234567890',
            'statusCallback' => 'https://example.com/status',
        ],
    ]);
});

it('can chain all fluent methods', function () {
    $message = (new TwilioSmsMessage)
        ->content('Hello World')
        ->from('+1234567890')
        ->messagingService('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX')
        ->mediaUrls(['https://example.com/image.jpg'])
        ->statusCallback('https://example.com/status')
        ->options(['extra' => 'option']);

    expect($message->content)->toBe('Hello World');
    expect($message->options['from'])->toBe('+1234567890');
    expect($message->options['messagingServiceSid'])->toBe('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
    expect($message->options['mediaUrls'])->toBe(['https://example.com/image.jpg']);
    expect($message->options['statusCallback'])->toBe('https://example.com/status');
    expect($message->options['extra'])->toBe('option');
});

it('creates empty message when no content provided', function () {
    $message = new TwilioSmsMessage;

    expect($message->content)->toBe('');
    expect($message->options)->toBe([]);
});
