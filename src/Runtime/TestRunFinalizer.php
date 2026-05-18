<?php

declare(strict_types=1);

namespace QueryGuard\Runtime;

use QueryGuard\Baseline\BaselineDiffer;
use QueryGuard\Baseline\BaselineStore;
use QueryGuard\Recorder\TestQueryProfile;
use QueryGuard\Reporter\ConsoleReporter;
use QueryGuard\Reporter\MarkdownReporter;

final class TestRunFinalizer
{
    /**
     * @param  array<string, TestQueryProfile>  $profiles
     */
    public static function run(array $profiles): void
    {
        $config = ConfigLoader::load();
        $store = new BaselineStore($config['baseline_path']);
        $mode = RunMode::get();

        if ($mode === RunMode::BASELINE) {
            $store->save($profiles);
            fwrite(STDOUT, "\n[QueryGuard] Baseline written: ".$store->path.' ('.count($profiles)." tests)\n");

            return;
        }

        $baseline = $store->load();
        $differ = new BaselineDiffer($config);
        $report = $differ->diff($profiles, $baseline);

        $reporter = ($config['reporter'] ?? 'console') === 'markdown'
            ? new MarkdownReporter
            : new ConsoleReporter;

        fwrite(STDOUT, $reporter->render($report));

        if ($mode === RunMode::REPORT) {
            return;
        }

        if (($config['fail_on_regression'] ?? true) && $report->hasFatal()) {
            // Defer the non-zero exit so PHPUnit can flush its own output first.
            register_shutdown_function(static fn () => exit(1));
        }
    }
}
