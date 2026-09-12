<?php

// Run against an installed Laravel consumer, never a production application.
$root = dirname(__DIR__);
$consumer = $argv[1] ?? $root.'/.cache/smoke';
require $consumer.'/vendor/autoload.php';
require $root.'/tests/Support/RecordingTransport.php';
require $root.'/tests/Support/TransportTwilioService.php';

use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Events\TwilioWebhookReceived;
use Citricguy\TwilioLaravel\Facades\Twilio;
use Citricguy\TwilioLaravel\Jobs\SendTwilioMessage;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsMessage;
use Citricguy\TwilioLaravel\Tests\Support\TransportTwilioService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as Notifications;
use Twilio\Security\RequestValidator;

$app = require $consumer.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$check = function (bool $condition, string $description): void {
    if (! $condition) {
        throw new RuntimeException($description);
    }
    echo 'PASS '.$description.PHP_EOL;
};
$check($app->bound('twilio-sms'), 'package discovered and service registered');
$check($app->routesAreCached() && $app->configurationIsCached(), 'application booted with configuration and routes cached');
config(['twilio-laravel.from' => '+15550000001', 'twilio-laravel.auth_token' => 'test-token', 'twilio-laravel.validate_webhook' => true, 'twilio-laravel.queue_messages' => false]);
$service = new TransportTwilioService;
Twilio::swap($service);
$notification = new class extends Notification
{
    public function via(object $notifiable): array
    {
        return ['twilioSms'];
    }

    public function toTwilioSms(object $notifiable): TwilioSmsMessage
    {
        return (new TwilioSmsMessage('smoke'))->messagingService('MG_EXPLICIT');
    }
};
Notifications::route('twilioSms', '+15550000002')->notify($notification);
$check(count($service->transport->requests) === 1 && $service->transport->requests[0]['data']['MessagingServiceSid'] === 'MG_EXPLICIT', 'notification reaches the SDK with explicit Messaging Service');
Bus::fake();
$count = 0;
Event::listen(TwilioMessageSending::class, function () use (&$count): void {
    $count++;
});
Twilio::queueMessage('+15550000002', 'queued smoke');
$job = Bus::dispatched(SendTwilioMessage::class)->first();
$check($job instanceof SendTwilioMessage && $count === 1, 'queue dispatch checks the sending listener once');
$restored = unserialize(serialize($job));
$restored->handle($service);
$check($count === 2 && count($service->transport->requests) === 2, 'serialized worker checks the sending listener once and sends once');
$eventPayload = null;
Event::listen(TwilioWebhookReceived::class, function ($event) use (&$eventPayload) {
    $eventPayload = $event->payload;

    return response('<Response/>', 200, ['Content-Type' => 'text/xml']);
});
$url = 'http://localhost/'.ltrim(config('twilio-laravel.webhook_path'), '/');
$payload = ['MessageSid' => 'SM_TEST', 'Body' => 'smoke'];
$signature = (new RequestValidator('test-token'))->computeSignature($url, $payload);
$kernel = $app->make(HttpKernel::class);
$request = Request::create($url, 'POST', $payload, server: ['HTTP_X_TWILIO_SIGNATURE' => $signature]);
$response = $kernel->handle($request);
$check($response->getStatusCode() === 200 && $response->getContent() === '<Response/>' && $eventPayload === $payload, 'signed webhook delivers payload and listener TwiML');
$kernel->terminate($request, $response);
$eventPayload = null;
$request = Request::create($url, 'POST', $payload, server: ['HTTP_X_TWILIO_SIGNATURE' => 'invalid']);
$response = $kernel->handle($request);
$check($response->getStatusCode() === 403 && $eventPayload === null, 'invalid signature is rejected before event dispatch');
$kernel->terminate($request, $response);
echo 'No live Twilio transport was used.'.PHP_EOL;
