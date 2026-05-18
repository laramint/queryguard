<?php

declare(strict_types=1);

namespace QueryGuard\Commands;

use Illuminate\Console\Command;

final class BaselineCommand extends Command
{
    protected $signature = 'queryguard:baseline {--phpunit= : Path to phpunit binary (defaults to vendor/bin/phpunit)}';

    protected $description = 'Run the test suite and (re)write the QueryGuard baseline file.';

    public function handle(): int
    {
        $bin = $this->option('phpunit') ?: 'vendor/bin/phpunit';
        $env = ['QUERYGUARD_MODE' => 'baseline'] + $_ENV;

        $this->info("[QueryGuard] Recording baseline via {$bin}...");

        $cmd = escapeshellcmd($bin);
        $envPrefix = '';
        foreach ($env as $k => $v) {
            if (! is_string($v)) {
                continue;
            }
            $envPrefix .= escapeshellarg("{$k}={$v}").' ';
        }

        $exit = 0;
        passthru("env {$envPrefix}{$cmd}", $exit);

        return $exit;
    }
}
