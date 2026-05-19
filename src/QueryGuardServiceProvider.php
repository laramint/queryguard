<?php

declare(strict_types=1);

namespace QueryGuard;

use Illuminate\Support\ServiceProvider;
use QueryGuard\Commands\BaselineCommand;
use QueryGuard\Commands\CheckCommand;
use QueryGuard\Recorder\QueryRecorder;

final class QueryGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queryguard.php', 'queryguard');
    }

    public function boot(): void
    {
        // Re-attempt listener registration now that the app is fully booted.
        // The PHPUnit extension's first attempt fires before setUp() and may
        // have been skipped because the container wasn't ready.
        if (QueryRecorder::isActive() || $this->app->runningUnitTests()) {
            // Each test creates a new Application with a new event dispatcher.
            // Call registerOnDispatcher() directly (not bootListener()) so the
            // listener is always registered on this app's fresh dispatcher.
            QueryRecorder::instance()->registerOnDispatcher($this->app->make('events'));
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/queryguard.php' => $this->app->configPath('queryguard.php'),
            ], 'queryguard-config');

            $this->commands([
                BaselineCommand::class,
                CheckCommand::class,
            ]);
        }
    }
}
