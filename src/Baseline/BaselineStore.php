<?php

declare(strict_types=1);

namespace QueryGuard\Baseline;

use QueryGuard\Recorder\TestQueryProfile;
use RuntimeException;

final class BaselineStore
{
    public const FORMAT_VERSION = 1;

    public function __construct(public readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @return array{version: int, tests: array<string, array{query_count: int, signatures: array<string, int>, max_duration_ms: float, total_duration_ms?: float}>}
     */
    public function load(): array
    {
        if (! $this->exists()) {
            return ['version' => self::FORMAT_VERSION, 'tests' => []];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read baseline file at {$this->path}");
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['tests']) || ! is_array($decoded['tests'])) {
            throw new RuntimeException("Baseline file at {$this->path} is malformed");
        }

        return $decoded;
    }

    /**
     * @param  array<string, TestQueryProfile>  $profiles
     */
    public function save(array $profiles): void
    {
        $tests = [];
        foreach ($profiles as $id => $profile) {
            $tests[$id] = $profile->toArray();
        }
        ksort($tests);

        $payload = [
            'version' => self::FORMAT_VERSION,
            'generated_at' => date(DATE_ATOM),
            'tests' => $tests,
        ];

        $dir = dirname($this->path);
        if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create directory {$dir}");
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode baseline JSON');
        }

        file_put_contents($this->path, $json."\n");
    }
}
