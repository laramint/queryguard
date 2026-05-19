<?php

declare(strict_types=1);

namespace QueryGuard\PHPUnit;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished as TestFinished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared as TestPrepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\TestRunner\Finished as RunnerFinished;
use PHPUnit\Event\TestRunner\FinishedSubscriber as RunnerFinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use QueryGuard\Attributes\QueryBudget;
use QueryGuard\Recorder\QueryRecorder;
use QueryGuard\Runtime\RunMode;
use QueryGuard\Runtime\TestRunFinalizer;

final class QueryGuardExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        // Authoritative "QueryGuard owns this suite" signal — set before any
        // Laravel app boots so the service provider always re-attaches the
        // query listener to each test's fresh event dispatcher.
        QueryRecorder::markActive();

        // Mode is read by the Finalizer at runner-finished time.
        // Pass via parameter or environment variable: QUERYGUARD_MODE=baseline|check.
        if ($parameters->has('mode')) {
            RunMode::set($parameters->get('mode'));
        }

        $facade->registerSubscribers(
            new class implements PreparedSubscriber
            {
                public function notify(TestPrepared $event): void
                {
                    QueryRecorder::instance()->bootListener();
                    QueryRecorder::instance()->startTest($event->test()->id());
                }
            },
            new class implements FinishedSubscriber
            {
                public function notify(TestFinished $event): void
                {
                    $test = $event->test();
                    if ($test instanceof TestMethod) {
                        try {
                            $attrs = (new \ReflectionMethod($test->className(), $test->methodName()))
                                ->getAttributes(QueryBudget::class);
                            if ($attrs !== []) {
                                $budget = $attrs[0]->newInstance();
                                QueryRecorder::instance()->setCurrentBudget($budget->max);
                            }
                        } catch (\ReflectionException) {
                            // method may not exist if the test was skipped/error'd early
                        }
                    }
                    QueryRecorder::instance()->endTest();
                }
            },
            new class implements RunnerFinishedSubscriber
            {
                public function notify(RunnerFinished $event): void
                {
                    TestRunFinalizer::run(QueryRecorder::instance()->profiles());
                }
            },
        );
    }
}
