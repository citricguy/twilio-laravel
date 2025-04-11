<?php

namespace Citricguy\TwilioLaravel;

use Citricguy\TwilioLaravel\Console\VerifyWebhookSetupCommand;
use Citricguy\TwilioLaravel\Http\Controllers\TwilioLaravelWebhookController;
use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Citricguy\TwilioLaravel\Notifications\TwilioCallChannel;
use Citricguy\TwilioLaravel\Notifications\TwilioSmsChannel;
use Citricguy\TwilioLaravel\Services\TwilioService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TwilioLaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();

        $this->publishes([
            __DIR__.'/../config/twilio-laravel.php' => config_path('twilio-laravel.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyWebhookSetupCommand::class,
            ]);
        }

        // Register the notification channels
        $this->registerNotificationChannel();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/twilio-laravel.php', 'twilio-laravel');

        // Register the Twilio service
        $this->app->singleton('twilio-sms', function ($app) {
            return new TwilioService;
        });
    }

    private function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            Route::post(config('twilio-laravel.webhook_path'), TwilioLaravelWebhookController::class)
                ->name('twilio-laravel.process-webhook');
        });
    }

    private function routeConfiguration(): array
    {
        return [
            'middleware' => [
                'api',
                VerifyTwilioWebhook::class,
            ],
        ];
    }

    /**
     * Register the Twilio notification channels.
     */
    private function registerNotificationChannel(): void
    {
        Notification::resolved(function (ChannelManager $service) {
            // Register SMS channel
            $service->extend(config('twilio-laravel.notifications.channel_name', 'twilioSms'), function ($app) {
                return new TwilioSmsChannel;
            });

            // Register Call channel
            $service->extend(config('twilio-laravel.notifications.call_channel_name', 'twilioCall'), function ($app) {
                return new TwilioCallChannel;
            });
        });
    }
}
