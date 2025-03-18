<?php

namespace Citricguy\TwilioLaravel\Helpers;

use Twilio\Security\RequestValidator;

class WebhookSignatureHelper
{
    /**
     * Generate a valid Twilio signature for the given URL and parameters.
     *
     * @param  string  $url  The full webhook URL
     * @param  array  $params  The request parameters
     * @param  string|null  $authToken  The Twilio auth token (uses config if not provided)
     * @return string The generated X-Twilio-Signature value
     */
    public static function generateValidSignature(string $url, array $params, ?string $authToken = null): string
    {
        $authToken = $authToken ?: config('twilio-laravel.auth_token');

        $validator = new RequestValidator($authToken);

        return $validator->computeSignature($url, $params);
    }

    /**
     * Verify if a signature is valid for the given URL and parameters.
     *
     * @param  string  $signature  The X-Twilio-Signature header value
     * @param  string  $url  The full webhook URL
     * @param  array  $params  The request parameters
     * @param  string|null  $authToken  The Twilio auth token (uses config if not provided)
     * @return bool Whether the signature is valid
     */
    public static function isValidSignature(string $signature, string $url, array $params, ?string $authToken = null): bool
    {
        $authToken = $authToken ?: config('twilio-laravel.auth_token');

        $validator = new RequestValidator($authToken);

        return $validator->validate($signature, $url, $params);
    }
}
