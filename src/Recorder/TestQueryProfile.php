<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

final class TestQueryProfile
{
    /** @var list<RecordedQuery> */
    public array $queries = [];

    /** @var list<string> */
    public array $violations = [];

    public ?int $queryBudget = null;

    public function __construct(public readonly string $testId) {}

    public function add(RecordedQuery $query): void
    {
        $this->queries[] = $query;
    }

    public function count(): int
    {
        return count($this->queries);
    }

    /** Count only SELECT queries — excludes factory/seed write operations. */
    public function selectCount(): int
    {
        $count = 0;
        foreach ($this->queries as $q) {
            if (
                ! str_starts_with($q->signature, 'insert ')
                && ! str_starts_with($q->signature, 'update ')
                && ! str_starts_with($q->signature, 'delete ')
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function isWrite(RecordedQuery $q): bool
    {
        return str_starts_with($q->signature, 'insert ')
            || str_starts_with($q->signature, 'update ')
            || str_starts_with($q->signature, 'delete ');
    }

    /**
     * Signature counts for all queries (used internally by differ for N+1 detection).
     *
     * @return array<string, int>
     */
    public function signatureCounts(): array
    {
        $counts = [];
        foreach ($this->queries as $q) {
            $counts[$q->signature] = ($counts[$q->signature] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /**
     * Signature counts excluding write operations — used for baseline storage
     * and budget enforcement so factory/seed INSERTs don't inflate the numbers.
     *
     * @return array<string, int>
     */
    public function readSignatureCounts(): array
    {
        $counts = [];
        foreach ($this->queries as $q) {
            if (! $this->isWrite($q)) {
                $counts[$q->signature] = ($counts[$q->signature] ?? 0) + 1;
            }
        }
        arsort($counts);

        return $counts;
    }

    public function maxDurationMs(): float
    {
        $max = 0.0;
        foreach ($this->queries as $q) {
            if ($q->durationMs > $max) {
                $max = $q->durationMs;
            }
        }

        return $max;
    }

    public function totalDurationMs(): float
    {
        $total = 0.0;
        foreach ($this->queries as $q) {
            $total += $q->durationMs;
        }

        return $total;
    }

    /**
     * @return array{query_count: int, signatures: array<string, int>, max_duration_ms: float, total_duration_ms: float}
     */
    public function toArray(): array
    {
        return [
            'query_count' => $this->selectCount(),
            'signatures' => $this->readSignatureCounts(),
            'max_duration_ms' => round($this->maxDurationMs(), 2),
            'total_duration_ms' => round($this->totalDurationMs(), 2),
        ];
    }
}
