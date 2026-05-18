<?php

declare(strict_types=1);

namespace QueryGuard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryGuard\Baseline\BaselineDiffer;
use QueryGuard\Baseline\Regression;
use QueryGuard\Recorder\RecordedQuery;
use QueryGuard\Recorder\TestQueryProfile;

final class BaselineDifferTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(): array
    {
        return [
            'tolerance' => ['extra_queries' => 2, 'extra_duration_ms' => 50],
            'n_plus_one' => ['threshold' => 2],
            'slow_query' => ['threshold_ms' => 100],
            'ignore' => ['signatures' => [], 'tests' => []],
        ];
    }

    private function profile(string $id, array $queries): TestQueryProfile
    {
        $p = new TestQueryProfile($id);
        foreach ($queries as $q) {
            $p->add(new RecordedQuery($q[0], $q[0], $q[1] ?? 1.0, 'testing'));
        }

        return $p;
    }

    public function test_no_regressions_when_within_tolerance(): void
    {
        $current = ['T::a' => $this->profile('T::a', [
            ['select * from users where id = ?'],
            ['select * from users where id = ?'],
            ['select * from posts where id = ?'],
        ])];
        $baseline = ['tests' => ['T::a' => [
            'query_count' => 3,
            'signatures' => [
                'select * from users where id = ?' => 2,
                'select * from posts where id = ?' => 1,
            ],
            'max_duration_ms' => 5.0,
        ]]];

        $report = (new BaselineDiffer($this->defaultConfig()))->diff($current, $baseline);

        self::assertTrue($report->isEmpty(), 'expected clean run');
    }

    public function test_flags_n_plus_one(): void
    {
        $current = ['T::a' => $this->profile('T::a', array_fill(0, 5, ['select * from users where id = ?']))];
        $baseline = ['tests' => []];

        $report = (new BaselineDiffer($this->defaultConfig()))->diff($current, $baseline);

        $kinds = array_map(fn (Regression $r) => $r->kind, $report->regressions);
        self::assertContains(Regression::KIND_N_PLUS_ONE, $kinds);
    }

    public function test_flags_query_count_regression_beyond_tolerance(): void
    {
        $current = ['T::a' => $this->profile('T::a', array_fill(0, 10, ['select * from t where id = ?']))];
        $baseline = ['tests' => ['T::a' => [
            'query_count' => 3,
            'signatures' => ['select * from t where id = ?' => 3],
            'max_duration_ms' => 1.0,
        ]]];

        $report = (new BaselineDiffer($this->defaultConfig()))->diff($current, $baseline);

        $kinds = array_map(fn (Regression $r) => $r->kind, $report->regressions);
        self::assertContains(Regression::KIND_QUERY_COUNT, $kinds);
        self::assertTrue($report->hasFatal());
    }

    public function test_flags_slow_query_as_warning_not_fatal(): void
    {
        $current = ['T::a' => $this->profile('T::a', [
            ['select * from huge', 250.0],
        ])];
        $baseline = ['tests' => ['T::a' => [
            'query_count' => 1,
            'signatures' => ['select * from huge' => 1],
            'max_duration_ms' => 10.0,
        ]]];

        $report = (new BaselineDiffer($this->defaultConfig()))->diff($current, $baseline);

        $slow = array_filter($report->regressions, fn (Regression $r) => $r->kind === Regression::KIND_SLOW_QUERY);
        self::assertNotEmpty($slow);
        foreach ($slow as $r) {
            self::assertFalse($r->fatal);
        }
    }

    public function test_ignore_pattern_silences_test(): void
    {
        $config = $this->defaultConfig();
        $config['ignore']['tests'] = ['Tests\\Unit\\*'];

        $current = ['Tests\\Unit\\Foo::test_bar' => $this->profile('Tests\\Unit\\Foo::test_bar', array_fill(0, 50, ['select 1']))];
        $baseline = ['tests' => []];

        $report = (new BaselineDiffer($config))->diff($current, $baseline);

        self::assertTrue($report->isEmpty());
    }
}
