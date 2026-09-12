<?php

namespace Citricguy\TwilioLaravel\Tests\Support;

use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\Client;
use Twilio\Http\Response;

final class RecordingTransport implements Client
{
    public array $requests = [];

    public int $status = 201;

    public array $response = ['sid' => 'SM_TEST', 'status' => 'queued', 'num_segments' => '1'];

    public function request(string $method, string $url, array $params = [], array $data = [], array $headers = [], ?string $user = null, ?string $password = null, ?int $timeout = null, ?AuthStrategy $authStrategy = null): Response
    {
        $this->requests[] = compact('method', 'url', 'params', 'data', 'headers');

        return new Response($this->status, json_encode($this->response, JSON_THROW_ON_ERROR));
    }
}
