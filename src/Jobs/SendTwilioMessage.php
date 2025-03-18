<?php

namespace Citricguy\TwilioLaravel\Jobs;

use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
     * @param string $to
     * @param string $message
     * @param array $options
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
     * @param TwilioService $twilioService
     * @return void
     */
    public function handle(TwilioService $twilioService)
    {
        $twilioService->sendMessageNow($this->to, $this->message, $this->options);
    }
}
