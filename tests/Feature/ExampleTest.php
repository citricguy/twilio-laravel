<?php

namespace Citricguy\TwilioLaravel\Tests\Feature;

use Illuminate\Support\Facades\Http;

it('blocks stray HTTP requests', function () {
    Http::get('https://example.com');
})->throws(\RuntimeException::class);
