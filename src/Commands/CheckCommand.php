<?php

declare(strict_types=1);

namespace QueryGuard\Commands;

use Illuminate\Console\Command;
use QueryGuard\Runtime\TestProcessEnv;

final class CheckCommand extends Command
{
    protected $signature = 'queryguard:check
                            {--phpunit= : Path to phpunit binary}
                            {--report : Print the report but do not fail the build}
                            {--markdown : Use the Markdown reporter (good for PR comments)}';

    protected $description = 'Run the test suite, diff against the baseline, and exit non-zero on regression.';

    public function handle(): int
    {
        $bin = $this->option('phpunit') ?: 'vendor/bin/phpunit';
        $mode = $this->option('report') ? 'report' : 'check';

        $env = [
            'QUERYGUARD_MODE' => $mode,
        ];
        if ($this->option('markdown')) {
            $env['QUERYGUARD_REPORTER'] = 'markdown';
        }

        $this->info("[QueryGuard] Running {$bin} in {$mode} mode...");

        $envPrefix = TestProcessEnv::prefix($env);

        $exit = 0;
        passthru($envPrefix.escapeshellcmd($bin), $exit);

        return $exit;
    }
}
