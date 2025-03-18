<?php

namespace Citricguy\TwilioLaravel\Tests;

use Citricguy\TwilioLaravel\TwilioLaravelServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp(); // Ensure Laravel's app is booted before using facades

        // Now it's safe to prevent stray HTTP requests
        Http::preventStrayRequests();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            TwilioLaravelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
