<?php

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Citricguy\TwilioLaravel\Jobs\SendTwilioCall;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Tests\Support\TransportTwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twilio\Exceptions\RestException;
use Twilio\Security\RequestValidator;

beforeEach(function () {
    $this->handler = new TestHandler;
    Log::swap(new Logger('test', [$this->handler]));
    config(['twilio-laravel.auth_token' => 'SECRET_TOKEN', 'twilio-laravel.from' => '+15551239876']);
});

it('never logs webhook secrets and retains safe diagnostics', function ($kind, $signatureState, $debug) {
    config(['twilio-laravel.debug' => $debug, 'twilio-laravel.validate_webhook' => true]);
    Event::fake([TwilioWebhookReceived::class]);
    $payload = match ($kind) {
        'sms' => ['MessageSid' => 'SM_SECRET', 'Body' => 'SECRET_BODY'],
        'mms' => ['MessageSid' => 'MM_SECRET', 'Body' => 'SECRET_BODY', 'NumMedia' => '1', 'MediaUrl0' => 'https://example.test/SECRET_MEDIA'],
        'status' => ['MessageSid' => 'SM_SECRET', 'MessageStatus' => 'SECRET_STATUS'],
        'voice' => ['CallSid' => 'CA_SECRET', 'CallStatus' => 'SECRET_STATUS', 'CallbackSource' => 'call-progress-events'],
    };
    $payload += ['From' => '+15551239876', 'Private' => 'SECRET_RAW'];
    $path = '/'.ltrim(config('twilio-laravel.webhook_path'), '/').'?z=SECRET_QUERY&a=x%20y';
    $validSignature = (new RequestValidator('SECRET_TOKEN'))->computeSignature('http://localhost'.$path, $payload);
    $signature = $signatureState === 'valid' ? $validSignature : 'SECRET_SIGNATURE';
    $headers = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_AUTHORIZATION' => 'SECRET_AUTH', 'HTTP_X_REQUEST_ID' => 'SECRET_ID'];
    if ($signatureState !== 'missing') {
        $headers['HTTP_X_TWILIO_SIGNATURE'] = $signature;
    }
    $response = $this->call('POST', $path, $payload, [], [], $headers, http_build_query($payload));
    $response->assertStatus($signatureState === 'valid' ? 202 : 403);
    if ($signatureState === 'valid') {
        Event::assertDispatched(TwilioWebhookReceived::class, fn ($event) => $event->payload['Private'] === 'SECRET_RAW');
    } else {
        Event::assertNotDispatched(TwilioWebhookReceived::class);
    }
    $records = $this->handler->getRecords();
    $encoded = json_encode($records, JSON_THROW_ON_ERROR);
    foreach (['SECRET_', 'SM_SECRET', 'MM_SECRET', 'CA_SECRET', '+15551239876', $validSignature] as $sentinel) {
        expect($encoded)->not->toContain($sentinel);
    }
    if ($signatureState !== 'valid' || $debug) {
        expect($records)->not->toBeEmpty()
            ->and($records[0]->context)->toMatchArray(['method' => 'POST', 'validation_enabled' => true, 'signature_present' => $signatureState !== 'missing', 'content_length' => strlen(http_build_query($payload))]);
    } else {
        expect($records)->toBe([]);
    }
})->with(['sms', 'mms', 'status', 'voice'])->with(['valid', 'invalid', 'missing'])->with([true, false]);

it('never logs outbound data on success failure or cancellation', function ($voice, $outcome, $debug) {
    config(['twilio-laravel.debug' => $debug]);
    $service = new TransportTwilioService;
    $service->transport->response['sid'] = 'PROVIDER_ID_SECRET';
    $options = ['statusCallback' => 'https://example.test/SECRET_URL', 'metadata' => ['private' => 'SECRET_METADATA']];
    if ($outcome === 'failure') {
        $service->transport->status = 400;
        $service->transport->response = ['code' => 21211, 'message' => 'SECRET_PROVIDER_ERROR +15551239876', 'more_info' => 'https://example.test/SECRET_ERROR'];
    }
    if ($outcome === 'application-failure') {
        Event::listen($voice ? TwilioCallSending::class : TwilioMessageSending::class, fn () => throw new RuntimeException('SECRET_APPLICATION_ERROR', 15551239876));
    }
    if (in_array($outcome, ['cancel', 'worker-cancel', 'queue-cancel'])) {
        Event::listen($voice ? TwilioCallSending::class : TwilioMessageSending::class, fn ($event) => $event->cancel('SECRET_REASON'));
    }
    try {
        if ($outcome === 'worker-cancel') {
            $job = $voice ? new SendTwilioCall('+15551239876', 'SECRET_BODY', $options) : new SendTwilioMessage('+15551239876', 'SECRET_BODY', $options);
            $job->handle($service);
        } else {
            $method = $outcome === 'queue-cancel' ? ($voice ? 'queueCall' : 'queueMessage') : ($voice ? 'makeCallNow' : 'sendMessageNow');
            $result = $service->$method('+15551239876', 'SECRET_BODY', $options);
            if (in_array($outcome, ['cancel', 'queue-cancel'])) {
                expect($result['reason'])->toBe('SECRET_REASON');
            }
        }
        expect($outcome)->not->toBeIn(['failure', 'application-failure']);
    } catch (RestException $e) {
        expect($outcome)->toBe('failure')->and($e->getMessage())->toContain('SECRET_PROVIDER_ERROR');
    } catch (RuntimeException $e) {
        expect($outcome)->toBe('application-failure')->and($e->getMessage())->toBe('SECRET_APPLICATION_ERROR');
    }
    $records = $this->handler->getRecords();
    expect(json_encode($records, JSON_THROW_ON_ERROR))->not->toContain('SECRET_', '+15551239876', 'PROVIDER_ID_SECRET', 'AC_TEST', 'test-token', '15551239876');
    if ($debug) {
        expect($records)->not->toBeEmpty();
        $context = end($records)->context;
        expect($context)->toHaveKey(match ($outcome) {
            'failure', 'application-failure' => 'error_code', 'success' => 'option_count', default => 'has_reason'
        });
    } else {
        expect($records)->toBe([]);
    }
})->with([false, true])->with(['success', 'failure', 'application-failure', 'cancel', 'worker-cancel', 'queue-cancel'])->with([true, false]);

it('does not echo arbitrary HTTP method names into diagnostics', function () {
    config(['twilio-laravel.validate_webhook' => true]);
    $request = Request::create('https://example.test/webhook', 'SECRET_METHOD');
    try {
        (new VerifyTwilioWebhook)->handle($request, fn () => throw new LogicException('must not dispatch'));
        $this->fail('Expected missing-signature rejection');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
    expect($this->handler->getRecords()[0]->context['method'])->toBe('OTHER')
        ->and(json_encode($this->handler->getRecords(), JSON_THROW_ON_ERROR))->not->toContain('SECRET_METHOD');
});
