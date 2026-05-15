<?php

declare(strict_types=1);

namespace QueryGuard\Reporter;

use QueryGuard\Baseline\RegressionReport;

final class ConsoleReporter
{
    public function render(RegressionReport $report): string
    {
        if ($report->isEmpty()) {
            return "\n[QueryGuard] No regressions detected.\n";
        }

        $lines = ["\n[QueryGuard] " . $report->count() . ' issue(s):'];
        foreach ($report->regressions as $r) {
            $marker = $r->fatal ? 'FAIL' : 'warn';
            $lines[] = sprintf('  [%s] %s — %s (%s)', $marker, $r->testId, $r->message, $r->kind);
        }

        return implode("\n", $lines) . "\n";
    }
}
