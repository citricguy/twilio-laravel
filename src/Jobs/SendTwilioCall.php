<?php

namespace Citricguy\TwilioLaravel\Jobs;

use Citricguy\TwilioLaravel\Events\TwilioCallSending;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTwilioCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The recipient phone number.
     *
     * @var string
     */
    public $to;

    /**
     * The TwiML URL for the call.
     *
     * @var string
     */
    public $url;

    /**
     * Additional options for the call.
     *
     * @var array<string, mixed>
     */
    public $options;

    /**
     * Create a new job instance.
     *
     * @param array<string, mixed> $options
     * @return void
     */
    public function __construct(string $to, string $url, array $options = [])
    {
        $this->to = $to;
        $this->url = $url;
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
        $sendingEvent = new TwilioCallSending($this->to, $this->url, $this->options);
        event($sendingEvent);

        // Check if the call was cancelled
        if ($sendingEvent->cancelled()) {
            if (config('twilio-laravel.debug', false)) {
                Log::info('Twilio: Queued call cancelled', [
                    'to' => $this->to,
                    'reason' => $sendingEvent->cancellationReason(),
                ]);
            }

            return;
        }

        $twilioService->makeCallNow($this->to, $this->url, $this->options);
    }
}
