<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Illuminate\Support\Facades\Http;

it('passes a basic test', function () {
    expect(true)->toBeTrue();
});

it('blocks stray HTTP requests', function () {
    Http::get('https://example.com');
})->throws(\RuntimeException::class);
