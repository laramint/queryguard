<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;

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

    /**
     * Register on a specific dispatcher instance (called by the service provider
     * on every new Laravel application so each test's fresh app gets its own listener).
     * Intentionally bypasses the $listening guard.
     */
    public function registerOnDispatcher(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event): void {
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

    /**
     * Attempt to register on the global container's events dispatcher.
     * Called by the PHPUnit PreparedSubscriber before setUp() boots the app —
     * returns silently if the container isn't ready yet. The service provider
     * calls registerOnDispatcher() directly once the app is fully booted.
     */
    public function bootListener(): void
    {
        if ($this->listening) {
            return;
        }

        $container = Container::getInstance();

        if ($container === null || ! $container->bound('events')) {
            return;
        }

        $this->registerOnDispatcher($container->make('events'));
    }

    public function startTest(string $testId): void
    {
        $this->current = new TestQueryProfile($testId);
    }

    public function setCurrentBudget(int $max): void
    {
        if ($this->current !== null) {
            $this->current->queryBudget = $max;
        }
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
