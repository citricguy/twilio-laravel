<?php

namespace Citricguy\TwilioLaravel\Facades;

use Citricguy\TwilioLaravel\Testing\TwilioServiceFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed sendMessage(string $to, string $message, array $options = [])
 * @method static mixed sendMessageNow(string $to, string $message, array $options = [])
 * @method static mixed queueMessage(string $to, string $message, array $options = [])
 * @method static void fake()
 * @method static void assertSent(callable|null $callback = null)
 * @method static void assertSentTo(string $recipient)
 * @method static void assertSentCount(int $count)
 * @method static void assertNothingSent()
 *
 * @see \Citricguy\TwilioLaravel\Services\TwilioService
 */
class Twilio extends Facade
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

    /**
     * Replace the bound instance with a fake.
     *
     * @return \Citricguy\TwilioLaravel\Testing\TwilioServiceFake
     */
    public static function fake()
    {
        $fake = new TwilioServiceFake();

        static::swap($fake);

        return $fake;
    }
}
