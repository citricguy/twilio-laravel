<?php

namespace Citricguy\TwilioLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class VerifyTwilioWebhook
{
    /**
     * Validate a Twilio request without logging its contents.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $this->webhookValidationEnabled()) {
            Log::info('Twilio webhook signature validation is disabled.', ['validation_enabled' => false]);

            return $next($request);
        }

        $authToken = config('twilio-laravel.auth_token');
        if (! is_string($authToken) || empty($authToken)) {
            Log::error('Twilio auth token is not configured.');
            abort(500, 'Twilio configuration error.');
        }

        $signature = $request->header('X-Twilio-Signature');
        $method = $request->method();
        // Only fixed labels, booleans and measured counts reach the logger.
        $context = [
            'method' => in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true) ? $method : 'OTHER',
            'route' => 'twilio-laravel.process-webhook',
            'validation_enabled' => true,
            'signature_present' => is_string($signature) && $signature !== '',
            'content_length' => strlen($request->getContent()),
        ];

        if (! is_string($signature) || $signature === '') {
            Log::warning('Missing Twilio signature header.', $context);
            abort(403, 'Missing Twilio signature header.');
        }

        // fullUrl()/getQueryString() normalize query order and encoding. Twilio signs
        // the original URL. Symfony applies the application's trusted proxy policy.
        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
        $query = $request->server->get('QUERY_STRING');
        if (is_string($query) && $query !== '') {
            $url .= '?'.$query;
        }

        $params = $request->isMethod('post') ? $request->post() : [];
        $body = $request->getContent();
        if ($request->isMethod('post') && $request->getContentTypeFormat() === 'form' && $body !== '') {
            // Global TrimStrings/ConvertEmptyStringsToNull may already have run.
            parse_str($body, $params);
        }

        $bodyHash = $request->query('bodySHA256');
        $hasBodyHash = $request->query->has('bodySHA256');
        $validInput = $hasBodyHash
            ? is_string($bodyHash) && strlen($bodyHash) === 64 && ctype_xdigit($bodyHash)
            : $this->validParameters($params);

        $validator = new RequestValidator($authToken);
        if (! $validInput || ! $validator->validate($signature, $url, $hasBodyHash ? $body : $params)) {
            Log::warning('Invalid Twilio webhook signature.', $context);
            abort(403, 'Invalid Twilio webhook signature.');
        }

        if (config('twilio-laravel.debug')) {
            Log::debug('Valid Twilio webhook signature.', $context);
        }

        return $next($request);
    }

    /** @param array<array-key, mixed> $params */
    private function validParameters(array $params): bool
    {
        foreach ($params as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! is_scalar($item) && $item !== null) {
                    return false;
                }
            }
        }

        return true;
    }

    private function webhookValidationEnabled(): bool
    {
        $legacyValue = config('twilio-laravel.validate_webhook');
        if (is_bool($legacyValue)) {
            return $legacyValue;
        }

        $configuredValue = config('twilio-laravel.validate_webhook_signature');

        return is_bool($configuredValue) ? $configuredValue : true;
    }
}
