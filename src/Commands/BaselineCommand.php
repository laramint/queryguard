<?php

declare(strict_types=1);

namespace QueryGuard\Commands;

use Illuminate\Console\Command;
use QueryGuard\Runtime\TestProcessEnv;

final class BaselineCommand extends Command
{
    protected $signature = 'queryguard:baseline {--phpunit= : Path to phpunit binary (defaults to vendor/bin/phpunit)}';

    protected $description = 'Run the test suite and (re)write the QueryGuard baseline file.';

    public function handle(): int
    {
        $bin = $this->option('phpunit') ?: 'vendor/bin/phpunit';

        $this->info("[QueryGuard] Recording baseline via {$bin}...");

        $envPrefix = TestProcessEnv::prefix(['QUERYGUARD_MODE' => 'baseline']);

        $exit = 0;
        passthru($envPrefix.escapeshellcmd($bin), $exit);

        return $exit;
    }
}
