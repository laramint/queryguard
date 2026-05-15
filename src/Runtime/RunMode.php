<?php

declare(strict_types=1);

namespace QueryGuard\Runtime;

final class RunMode
{
    public const BASELINE = 'baseline';
    public const CHECK = 'check';
    public const REPORT = 'report';

    private static ?string $mode = null;

    public static function set(string $mode): void
    {
        self::$mode = $mode;
    }

    public static function get(): string
    {
        if (self::$mode !== null) {
            return self::$mode;
        }

        $env = getenv('QUERYGUARD_MODE');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return self::CHECK;
    }
}
