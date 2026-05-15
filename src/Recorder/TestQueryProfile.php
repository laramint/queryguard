<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

final class TestQueryProfile
{
    /** @var list<RecordedQuery> */
    public array $queries = [];

    /** @var list<string> */
    public array $violations = [];

    public function __construct(public readonly string $testId)
    {
    }

    public function add(RecordedQuery $query): void
    {
        $this->queries[] = $query;
    }

    public function count(): int
    {
        return count($this->queries);
    }

    /**
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
            'query_count' => $this->count(),
            'signatures' => $this->signatureCounts(),
            'max_duration_ms' => round($this->maxDurationMs(), 2),
            'total_duration_ms' => round($this->totalDurationMs(), 2),
        ];
    }
}
