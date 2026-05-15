<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

final class QueryRecorder
{
    private static ?self $instance = null;

    private ?TestQueryProfile $current = null;

    /** @var array<string, TestQueryProfile> */
    private array $profiles = [];

    private bool $listening = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function bootListener(): void
    {
        if ($this->listening) {
            return;
        }

        DB::listen(function (QueryExecuted $event): void {
            if ($this->current === null) {
                return;
            }
            $this->current->add(new RecordedQuery(
                signature: QuerySignature::normalize($event->sql),
                rawSql: $event->sql,
                durationMs: (float) $event->time,
                connection: $event->connectionName,
            ));
        });

        $this->listening = true;
    }

    public function startTest(string $testId): void
    {
        $this->current = new TestQueryProfile($testId);
    }

    public function endTest(): ?TestQueryProfile
    {
        $finished = $this->current;
        if ($finished !== null) {
            $this->profiles[$finished->testId] = $finished;
        }
        $this->current = null;

        return $finished;
    }

    /**
     * @return array<string, TestQueryProfile>
     */
    public function profiles(): array
    {
        return $this->profiles;
    }

    public function reset(): void
    {
        $this->current = null;
        $this->profiles = [];
    }
}
