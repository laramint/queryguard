<?php

declare(strict_types=1);

namespace QueryGuard\Reporter;

use QueryGuard\Baseline\RegressionReport;

final class MarkdownReporter
{
    public function render(RegressionReport $report): string
    {
        if ($report->isEmpty()) {
            return "### QueryGuard\n\n✅ No query regressions detected.\n";
        }

        $byTest = [];
        foreach ($report->regressions as $r) {
            $byTest[$r->testId][] = $r;
        }

        $out = "### QueryGuard — " . $report->count() . " issue(s)\n\n";
        foreach ($byTest as $testId => $items) {
            $out .= "**`{$testId}`**\n\n";
            foreach ($items as $r) {
                $icon = $r->fatal ? '❌' : '⚠️';
                $out .= "- {$icon} *{$r->kind}* — {$r->message}\n";
            }
            $out .= "\n";
        }

        return $out;
    }
}
