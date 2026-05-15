<?php

declare(strict_types=1);

namespace QueryGuard;

use Illuminate\Support\ServiceProvider;
use QueryGuard\Commands\BaselineCommand;
use QueryGuard\Commands\CheckCommand;

final class QueryGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/queryguard.php', 'queryguard');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/queryguard.php' => $this->app->configPath('queryguard.php'),
            ], 'queryguard-config');

            $this->commands([
                BaselineCommand::class,
                CheckCommand::class,
            ]);
        }
    }
}
