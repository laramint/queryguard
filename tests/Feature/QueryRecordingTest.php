<?php

declare(strict_types=1);

namespace QueryGuard\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use QueryGuard\QueryGuardServiceProvider;
use QueryGuard\Recorder\QueryRecorder;

final class QueryRecordingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [QueryGuardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The PHPUnit extension already marks the recorder active; make the
        // intent explicit and isolate this test from suite-wide state.
        QueryRecorder::markActive();
        QueryRecorder::instance()->reset();

        $this->migrateUsers();
    }

    private function migrateUsers(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
    }

    public function test_listener_captures_queries_run_inside_a_test(): void
    {
        $recorder = QueryRecorder::instance();
        $recorder->startTest('T::a');

        DB::table('users')->insert(['name' => 'Ada']);
        DB::table('users')->insert(['name' => 'Linus']);
        DB::table('users')->get();

        // Simulated N+1: same SELECT shape repeated.
        foreach (DB::table('users')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->first();
        }

        $profile = $recorder->endTest();

        self::assertNotNull($profile);
        self::assertGreaterThan(0, $profile->count(), 'no queries were recorded — listener never attached');

        $signatures = $profile->signatureCounts();
        $repeated = $signatures['select * from users where id = ? limit ?'] ?? 0;
        self::assertSame(2, $repeated, 'N+1 SELECT should be recorded twice');

        // selectCount excludes the INSERT writes.
        self::assertSame(4, $profile->selectCount());
    }

    public function test_capture_still_works_after_application_refresh(): void
    {
        // Each RefreshDatabase test rebuilds the app with a *new* event
        // dispatcher — the exact scenario that previously recorded 0 queries.
        $this->refreshApplication();
        $this->migrateUsers();

        $recorder = QueryRecorder::instance();
        $recorder->startTest('T::b');

        DB::table('users')->get();

        $profile = $recorder->endTest();

        self::assertNotNull($profile);
        self::assertSame(1, $profile->count(), 'single query must be counted exactly once (no double-registration)');
    }
}
