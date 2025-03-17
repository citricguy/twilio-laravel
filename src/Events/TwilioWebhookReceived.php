<?php

namespace Citricguy\TwilioLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TwilioWebhookReceived
{

    use Dispatchable;

    public function __construct()
    {

    }
}