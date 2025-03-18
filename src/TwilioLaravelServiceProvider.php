<?php

namespace Citricguy\TwilioLaravel;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Citricguy\TwilioLaravel\Http\Middleware\VerifyTwilioWebhook;
use Citricguy\TwilioLaravel\Http\Controllers\TwilioLaravelWebhookController;
use Citricguy\TwilioLaravel\Console\VerifyWebhookSetupCommand;

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
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/twilio-laravel.php', 'twilio-laravel');
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
}