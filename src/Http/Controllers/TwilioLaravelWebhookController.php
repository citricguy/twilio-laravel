<?php

namespace Citricguy\TwilioLaravel\Http\Controllers;

use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TwilioLaravelWebhookController
{
    /**
     * Handle the incoming Twilio webhook request.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        if (config('twilio-laravel.debug')) {
            Log::debug('Twilio webhook received', ['field_count' => count($payload), 'has_media' => isset($payload['MediaUrl0'])]);
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
