<?php

namespace Citricguy\TwilioLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class VerifyTwilioWebhook
{
    /**
     * Handle an incoming request and validate the Twilio webhook signature.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip validation if explicitly disabled
        if (config('twilio-laravel.validate_webhook') === false) {
            Log::info('Twilio webhook signature validation is disabled.');

            return $next($request);
        }

        $authToken = config('twilio-laravel.auth_token');

        if (empty($authToken)) {
            Log::error('Twilio auth token is not configured.');
            abort(500, 'Twilio configuration error.');
        }

        // Get the validator
        $validator = new RequestValidator($authToken);

        // Get the signature from the header
        $signature = $request->header('X-Twilio-Signature');

        if (empty($signature)) {
            Log::warning('Missing Twilio signature header.');
            abort(403, 'Missing Twilio signature header.');
        }

        // The full URL of the request
        $url = $request->fullUrl();

        // For POST requests, use request parameters
        $params = $request->isMethod('post') ? $request->post() : [];

        // Validate the request
        if (! $validator->validate($signature, $url, $params)) {
            $rawBody = $request->getContent();
            Log::warning('Invalid Twilio webhook signature.', [
                'url' => $url,
                'signature' => $signature,
                'is_valid' => false,
                'params' => $params,
                'headers' => $request->headers->all(),
                'method' => $request->method(),
                'raw_body' => $rawBody,
                'query' => $request->query(),
                'twilio_debug' => config('twilio-laravel.debug'),
            ]);

            abort(403, 'Invalid Twilio webhook signature.');
        }

        if (config('twilio-laravel.debug')) {
            Log::debug('Valid Twilio webhook signature.');
        }

        return $next($request);
    }
}
