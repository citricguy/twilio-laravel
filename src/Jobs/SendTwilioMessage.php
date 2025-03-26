<?php

namespace Citricguy\TwilioLaravel\Jobs;

use Citricguy\TwilioLaravel\Events\TwilioMessageSending;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTwilioMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The recipient phone number.
     *
     * @var string
     */
    public $to;

    /**
     * The message content.
     *
     * @var string
     */
    public $message;

    /**
     * Additional options for the message.
     *
     * @var array
     */
    public $options;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $to, string $message, array $options = [])
    {
        $this->to = $to;
        $this->message = $message;
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TwilioService $twilioService)
    {
        // One more chance to cancel before sending
        $sendingEvent = new TwilioMessageSending($this->to, $this->message, $this->options);
        event($sendingEvent);

        // Check if the message was cancelled
        if ($sendingEvent->cancelled()) {
            if (config('twilio-laravel.debug', false)) {
                Log::info('Twilio: Queued message cancelled', [
                    'to' => $this->to,
                    'reason' => $sendingEvent->cancellationReason(),
                ]);
            }

            return;
        }

        $twilioService->sendMessageNow($this->to, $this->message, $this->options);
    }
}
