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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $authToken = config('twilio-laravel.auth_token');

        if (empty($authToken)) {
            Log::error('Twilio auth token is not configured.');
            abort(500, 'Twilio configuration error.');
        }

        $validator = new RequestValidator($authToken);

        // The full URL of the request; adjust if you have any proxies or load balancers.
        $url = $request->fullUrl();
        $params = $request->all();
        $signature = $request->header('X-Twilio-Signature');

        if (!$validator->validate($signature, $url, $params)) {
            Log::warning('Invalid Twilio webhook signature.', [
                'url'       => $url,
                'params'    => $params,
                'signature' => $signature,
            ]);
            abort(403, 'Invalid Twilio webhook signature.');
        }

        return $next($request);
    }
}