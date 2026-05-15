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

        // 1. Try the Laravel config helper (available during an active app boot).
        if (function_exists('config')) {
            try {
                $live = config('queryguard');
                if (is_array($live) && $live !== []) {
                    self::$cached = array_replace_recursive($defaults, $live);
                    return self::$cached;
                }
            } catch (\Throwable) {
                // Fall through — config() is unavailable when called from
                // RunnerFinishedSubscriber (all test apps have been torn down).
            }
        }

        // 2. Use a cached result from when the app WAS running.
        if (self::$cached !== null) {
            return self::$cached;
        }

        // 3. Try to require the published config file directly from disk.
        $appConfig = getcwd() . '/config/queryguard.php';
        if (is_file($appConfig)) {
            try {
                $published = require $appConfig;
                if (is_array($published) && $published !== []) {
                    return self::resolveDefaults(array_replace_recursive($defaults, $published));
                }
            } catch (\Throwable) {
                // Malformed config — fall through to defaults.
            }
        }

        return self::resolveDefaults($defaults);
    }

    /** @var array<string, mixed>|null */
    private static ?array $cached = null;

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
