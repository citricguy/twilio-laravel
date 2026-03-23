<?php

namespace Citricguy\TwilioLaravel\Helpers;

use Twilio\Security\RequestValidator;

class WebhookSignatureHelper
{
    /**
     * Generate a valid Twilio signature for the given URL and parameters.
     *
     * @param string $url The full webhook URL
     * @param array<string, string> $params The request parameters
     * @param string|null $authToken The Twilio auth token (uses config if not provided)
     * @return string The generated X-Twilio-Signature value
     */
    public static function generateValidSignature(string $url, array $params, ?string $authToken = null): string
    {
        $validator = new RequestValidator(self::resolveAuthToken($authToken));

        return $validator->computeSignature($url, $params);
    }

    /**
     * Verify if a signature is valid for the given URL and parameters.
     *
     * @param string $signature The X-Twilio-Signature header value
     * @param string $url The full webhook URL
     * @param array<string, string> $params The request parameters
     * @param string|null $authToken The Twilio auth token (uses config if not provided)
     * @return bool Whether the signature is valid
     */
    public static function isValidSignature(string $signature, string $url, array $params, ?string $authToken = null): bool
    {
        $validator = new RequestValidator(self::resolveAuthToken($authToken));

        return $validator->validate($signature, $url, $params);
    }

    private static function resolveAuthToken(?string $authToken): string
    {
        if (is_string($authToken) && $authToken !== '') {
            return $authToken;
        }

        $configToken = config('twilio-laravel.auth_token');
        if (is_string($configToken) && $configToken !== '') {
            return $configToken;
        }

        throw new \InvalidArgumentException('Twilio auth token must be configured or explicitly provided.');
    }
}
