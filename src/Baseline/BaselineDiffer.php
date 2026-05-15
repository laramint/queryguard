<?php

declare(strict_types=1);

namespace QueryGuard\Baseline;

use QueryGuard\Recorder\TestQueryProfile;

final class BaselineDiffer
{
    /**
     * @param array{tolerance: array{extra_queries: int, extra_duration_ms: int}, n_plus_one: array{threshold: int}, slow_query: array{threshold_ms: int}, ignore: array{signatures: list<string>, tests: list<string>}} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string, TestQueryProfile> $current
     * @param array{tests: array<string, array{query_count: int, signatures: array<string, int>, max_duration_ms: float}>} $baseline
     */
    public function diff(array $current, array $baseline): RegressionReport
    {
        $regressions = [];
        $tolExtraQueries = $this->config['tolerance']['extra_queries'];
        $tolExtraDuration = $this->config['tolerance']['extra_duration_ms'];
        $nPlusOneThreshold = $this->config['n_plus_one']['threshold'];
        $slowMs = $this->config['slow_query']['threshold_ms'];
        $ignoreTests = $this->config['ignore']['tests'] ?? [];
        $ignoreSignatures = $this->config['ignore']['signatures'] ?? [];

        $baseTests = $baseline['tests'] ?? [];

        foreach ($current as $id => $profile) {
            if ($this->matchesAny($id, $ignoreTests)) {
                continue;
            }

            // @QueryBudget enforcement — always fatal, independent of baseline.
            // Only SELECT queries count against the budget (INSERTs/UPDATEs/DELETEs
            // are factory/seed setup noise, not application query cost).
            if ($profile->queryBudget !== null && $profile->selectCount() > $profile->queryBudget) {
                $regressions[] = new Regression(
                    $id,
                    Regression::KIND_BUDGET_EXCEEDED,
                    sprintf('Query budget exceeded: %d SELECT queries > max %d', $profile->selectCount(), $profile->queryBudget),
                );
            }

            // Always-on intra-test detectors.
            foreach ($profile->signatureCounts() as $sig => $count) {
                if ($this->matchesAny($sig, $ignoreSignatures)) {
                    continue;
                }
                // N+1 is a SELECT concern; repeated writes (factory setup, bulk
                // seeds) are a separate issue and should not be flagged here.
                if (str_starts_with($sig, 'insert ') || str_starts_with($sig, 'update ') || str_starts_with($sig, 'delete ')) {
                    continue;
                }
                if ($count > $nPlusOneThreshold) {
                    $regressions[] = new Regression(
                        $id,
                        Regression::KIND_N_PLUS_ONE,
                        sprintf('N+1 detected: signature executed %dx — "%s"', $count, $sig),
                    );
                }
            }
            foreach ($profile->queries as $q) {
                if ($q->durationMs >= $slowMs) {
                    $regressions[] = new Regression(
                        $id,
                        Regression::KIND_SLOW_QUERY,
                        sprintf('Slow query (%.1fms ≥ %dms): %s', $q->durationMs, $slowMs, $q->signature),
                        fatal: false,
                    );
                }
            }

            // Baseline diff.
            if (! isset($baseTests[$id])) {
                $regressions[] = new Regression(
                    $id,
                    Regression::KIND_NEW_TEST,
                    sprintf('New test (no baseline): %d queries, %.1fms max', $profile->selectCount(), $profile->maxDurationMs()),
                    fatal: false,
                );
                continue;
            }

            $base = $baseTests[$id];
            $currentCount = $profile->selectCount();
            $baseCount = (int) $base['query_count'];
            if ($currentCount > $baseCount + $tolExtraQueries) {
                $regressions[] = new Regression(
                    $id,
                    Regression::KIND_QUERY_COUNT,
                    sprintf('Query count regressed: %d → %d (+%d, tolerance +%d)', $baseCount, $currentCount, $currentCount - $baseCount, $tolExtraQueries),
                );
            }

            $currentMax = $profile->maxDurationMs();
            $baseMax = (float) ($base['max_duration_ms'] ?? 0);
            if ($currentMax > $baseMax + $tolExtraDuration) {
                $regressions[] = new Regression(
                    $id,
                    Regression::KIND_DURATION,
                    sprintf('Max query duration regressed: %.1fms → %.1fms (+%.1fms, tolerance +%dms)', $baseMax, $currentMax, $currentMax - $baseMax, $tolExtraDuration),
                    fatal: false,
                );
            }

            $currentSigs = $profile->readSignatureCounts();
            $baseSigs = $base['signatures'] ?? [];
            foreach ($currentSigs as $sig => $count) {
                if ($this->matchesAny($sig, $ignoreSignatures)) {
                    continue;
                }
                if (! isset($baseSigs[$sig])) {
                    $regressions[] = new Regression(
                        $id,
                        Regression::KIND_NEW_SIGNATURE,
                        sprintf('New query signature (%dx): %s', $count, $sig),
                        fatal: false,
                    );
                }
            }
        }

        return new RegressionReport($regressions);
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
