<?php

declare(strict_types=1);

namespace QueryGuard\Runtime;

final class TestProcessEnv
{
    /**
     * Build an `env` command prefix that runs the test binary with a clean
     * environment.
     *
     * When QueryGuard's Artisan commands spawn PHPUnit, the parent process has
     * already booted Laravel. If the app enables Dotenv's putenv (common), every
     * `.env` value (e.g. DB_CONNECTION=mysql) is a real OS env var that the
     * subprocess inherits and which overrides `phpunit.xml`'s `<env>` test
     * settings. We strip exactly the keys defined in the app's `.env` so the
     * test process sees the same environment a direct `vendor/bin/phpunit` run
     * would, then layer on the QueryGuard vars.
     *
     * @param  array<string, string>  $set
     */
    public static function prefix(array $set, ?string $envFile = null): string
    {
        $prefix = 'env';

        foreach (self::dotenvKeys($envFile) as $key) {
            $prefix .= ' -u '.escapeshellarg($key);
        }

        foreach ($set as $k => $v) {
            $prefix .= ' '.escapeshellarg("{$k}={$v}");
        }

        return $prefix.' ';
    }

    /**
     * @return list<string>
     */
    private static function dotenvKeys(?string $envFile): array
    {
        $envFile ??= (function_exists('base_path') ? base_path('.env') : getcwd().'/.env');

        if (! is_file($envFile)) {
            return [];
        }

        $contents = file_get_contents($envFile);
        if ($contents === false) {
            return [];
        }

        preg_match_all('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $contents, $matches);

        return array_values(array_unique($matches[1]));
    }
}
