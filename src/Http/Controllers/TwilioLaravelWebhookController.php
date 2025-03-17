<?php

namespace Citricguy\TwilioLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;

class TwilioLaravelWebhookController
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        TwilioWebhookReceived::dispatch();

        return response()->json(['success'])->setStatusCode(202);
    }
}