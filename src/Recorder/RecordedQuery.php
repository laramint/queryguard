<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

final class RecordedQuery
{
    public function __construct(
        public readonly string $signature,
        public readonly string $rawSql,
        public readonly float $durationMs,
        public readonly string $connection,
    ) {
    }
}
