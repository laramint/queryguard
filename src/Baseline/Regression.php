<?php

declare(strict_types=1);

namespace QueryGuard\Baseline;

final class Regression
{
    public const KIND_NEW_TEST = 'new_test';

    public const KIND_QUERY_COUNT = 'query_count';

    public const KIND_DURATION = 'duration';

    public const KIND_NEW_SIGNATURE = 'new_signature';

    public const KIND_N_PLUS_ONE = 'n_plus_one';

    public const KIND_SLOW_QUERY = 'slow_query';

    public const KIND_BUDGET_EXCEEDED = 'budget_exceeded';

    public function __construct(
        public readonly string $testId,
        public readonly string $kind,
        public readonly string $message,
        public readonly bool $fatal = true,
    ) {}
}
