<?php

namespace Citricguy\TwilioLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed sendMessage(string $to, string $message, array $options = [])
 * @method static mixed sendMessageNow(string $to, string $message, array $options = [])
 * @method static mixed queueMessage(string $to, string $message, array $options = [])
 *
 * @see \Citricguy\TwilioLaravel\Services\TwilioService
 */
class TwilioSms extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'twilio-sms';
    }
}
