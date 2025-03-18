<?php

namespace Citricguy\TwilioLaravel\Http\Controllers;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioLaravelWebhookController
{
    /**
     * Handle the incoming Twilio webhook request.
     */
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();

        if (config('twilio-laravel.debug')) {
            Log::debug('Twilio webhook received', ['payload' => $payload]);
        }

        // Dispatch event with the webhook payload
        TwilioWebhookReceived::dispatch($payload);

        return response()->json(['success' => true, 'message' => 'Webhook received'])->setStatusCode(202);
    }
}
