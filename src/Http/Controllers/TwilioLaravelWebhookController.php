<?php

namespace Citricguy\TwilioLaravel\Http\Controllers;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioLaravelWebhookController
{
    /**
     * Handle the incoming Twilio webhook request.
     */
    public function __invoke(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $payload = $request->all();

        if (config('twilio-laravel.debug')) {
            Log::debug('Twilio webhook received', ['payload' => $payload]);
        }

        // Create and dispatch the event
        $event = new TwilioWebhookReceived($payload);
        $responses = event($event);

        // Check if any listener returned a response
        if (is_array($responses)) {
            foreach ($responses as $response) {
                if ($response instanceof Response) {
                    return $response;
                }
            }
        }

        // Default response if no listener returned anything
        return response()->json(['success' => true, 'message' => 'Webhook received'])->setStatusCode(202);
    }
}
