<?php

declare(strict_types=1);

namespace QueryGuard\Baseline;

final class RegressionReport
{
    /**
     * @param list<Regression> $regressions
     */
    public function __construct(public readonly array $regressions = [])
    {
    }

    public function isEmpty(): bool
    {
        return $this->regressions === [];
    }

    public function hasFatal(): bool
    {
        foreach ($this->regressions as $r) {
            if ($r->fatal) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->regressions);
    }
}
