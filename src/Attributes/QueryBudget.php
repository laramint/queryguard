<?php

declare(strict_types=1);

namespace QueryGuard\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class QueryBudget
{
    public function __construct(
        public readonly int $max,
        public readonly ?int $maxDurationMs = null,
    ) {}
}
