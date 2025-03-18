<?php

namespace Citricguy\TwilioLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class VerifyWebhookSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:verify-webhook-setup 
                            {--url= : The base URL of your application}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify your Twilio webhook configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verifying Twilio webhook configuration...');

        // Check auth token is configured
        $authToken = config('twilio-laravel.auth_token');
        if (empty($authToken) || $authToken === 'your-twilio-auth-token') {
            $this->error('❌ Auth token is not configured properly!');
            $this->warn('Set TWILIO_TOKEN in your .env file with your actual Twilio auth token');
        } else {
            $this->info('✅ Auth token is configured');
        }

        // Check webhook path
        $webhookPath = config('twilio-laravel.webhook_path');
        if (empty($webhookPath)) {
            $this->error('❌ Webhook path is not configured!');
        } else {
            $this->info("✅ Webhook path is set to: $webhookPath");
        }

        // Validate full URL
        $baseUrl = $this->option('url') ?: URL::to('/');
        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($webhookPath, '/');
        $this->info("📌 Your full webhook URL should be: $fullUrl");
        
        // Check validation setting
        $validationEnabled = config('twilio-laravel.validate_webhook');
        if ($validationEnabled) {
            $this->info('✅ Webhook signature validation is ENABLED');
        } else {
            $this->warn('⚠️ Webhook signature validation is DISABLED (not recommended for production)');
        }

        $this->newLine();
        $this->info('To verify in production:');
        $this->line('1. Set up this URL in your Twilio console: ' . $fullUrl);
        $this->line('2. Send a test message through Twilio to trigger a webhook');
        $this->line('3. Check your Laravel logs to ensure signatures are validating properly');
    }
}
