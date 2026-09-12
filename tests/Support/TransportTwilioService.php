<?php

namespace Citricguy\TwilioLaravel\Tests\Support;

use Citricguy\TwilioLaravel\Services\TwilioService;
use Twilio\Rest\Client;

final class TransportTwilioService extends TwilioService
{
    public function __construct(public RecordingTransport $transport = new RecordingTransport)
    {
        $this->client = new Client('AC_TEST', 'test-token', httpClient: $transport);
    }
}
