<?php

declare(strict_types=1);

namespace QueryGuard\Runtime;

final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        $defaults = require dirname(__DIR__, 2) . '/config/queryguard.php';

        // When running under a Laravel app, prefer the published/merged config.
        if (function_exists('config')) {
            try {
                $live = config('queryguard');
                if (is_array($live) && $live !== []) {
                    return array_replace_recursive($defaults, $live);
                }
            } catch (\Throwable) {
                // Fall through to defaults — config() may not be available outside an app boot.
            }
        }

        return self::resolveDefaults($defaults);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function resolveDefaults(array $config): array
    {
        // The default baseline_path uses base_path() which only works inside a Laravel app.
        // When loaded outside (e.g. in PHPUnit extension before app bootstrap), fall back to cwd.
        if (! is_string($config['baseline_path'] ?? null)) {
            $config['baseline_path'] = getcwd() . '/tests/.queryguard-baseline.json';
        }

        return $config;
    }
}
