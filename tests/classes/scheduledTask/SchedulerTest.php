<?php

/**
 * @file tests/classes/scheduledTask/SchedulerTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SchedulerTest
 *
 * @see \PKP\scheduledTask\PKPScheduler
 * @see \PKP\scheduledTask\ScheduleTaskRunner
 * @see \APP\scheduler\Scheduler
 *
 * @brief Tests for the custom PKP wiring around Laravel's scheduler:
 *  - addSchedule() dedup contract
 *  - ScheduleTaskRunner failure isolation
 *  - plugin schedule registration via the HasTaskScheduler interface
 *  - a due task actually running in both web and CLI mode
 */

namespace PKP\tests\classes\scheduledTask;

use APP\scheduler\Scheduler;
use Exception;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\PKPContainer;
use PKP\core\Registry;
use PKP\plugins\GenericPlugin;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\PKPScheduler;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduleTaskRunner;
use PKP\tests\PKPTestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPScheduler::class)]
#[CoversClass(Scheduler::class)]
#[CoversClass(ScheduleTaskRunner::class)]
class SchedulerTest extends PKPTestCase
{
    protected function tearDown(): void
    {
        // The plugin registry is process-global; clear it so nothing leaks even
        // though each test method already runs in its own process.
        Registry::delete('plugins');

        parent::tearDown();
    }

    //
    // Group A: PKPScheduler::addSchedule() dedup contract
    //

    /**
     * A task whose class already matches a registered event's display name must
     * not be registered again; the existing Event is returned instead.
     */
    public function testAddScheduleReturnsExistingEventForKnownTask(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        // Pre-register an event identified by the task's class name, mirroring how
        // registerSchedules() names its events (->name(TaskClass::class)).
        $existing = $schedule->call(fn () => null)->name(SchedulerTestTask::class);
        $countBefore = count($schedule->events());

        $returned = $scheduler->addSchedule(new SchedulerTestTask());

        // Same Event instance returned, and no duplicate event was added.
        $this->assertSame($existing, $returned);
        $this->assertCount($countBefore, $schedule->events());
    }

    /**
     * A task with no matching registered event must result in a brand-new event.
     */
    public function testAddScheduleCreatesNewEventForUnknownTask(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        $countBefore = count($schedule->events());

        $returned = $scheduler->addSchedule(new SchedulerTestTask());

        $this->assertInstanceOf(Event::class, $returned);
        $this->assertCount($countBefore + 1, $schedule->events());
    }

    //
    // Group B: ScheduleTaskRunner::runEvent() failure isolation + lifecycle
    //

    /**
     * A task that throws must be reported and isolated: the throwable is handed to
     * the ExceptionHandler, a ScheduledTaskFailed event is dispatched, and the
     * exception never propagates out of the runner.
     */
    public function testRunEventReportsAndIsolatesFailure(): void
    {
        $schedule = new Schedule();
        $event = $schedule->call(fn () => throw new Exception('boom'));

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskStarting::class))->once();
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskFailed::class))->once();
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskFinished::class))->never();

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')
            ->with(Mockery::on(fn ($e) => $e instanceof Exception && $e->getMessage() === 'boom'))
            ->once();

        $runner = new ScheduleTaskRunner($schedule, $dispatcher, Mockery::mock(Cache::class), $handler);

        // runEvent() is protected; exercise it directly. It must not re-throw.
        $this->invokeRunEvent($runner, $event);

        // If we got here without an exception, isolation held.
        $this->assertTrue(true);
    }

    /**
     * A task that succeeds must dispatch the starting + finished lifecycle events
     * and must not be reported as a failure.
     */
    public function testRunEventDispatchesLifecycleOnSuccess(): void
    {
        $schedule = new Schedule();
        $event = $schedule->call(fn () => null);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskStarting::class))->once();
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskFinished::class))->once();
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(ScheduledTaskFailed::class))->never();

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->never();

        $runner = new ScheduleTaskRunner($schedule, $dispatcher, Mockery::mock(Cache::class), $handler);

        $this->invokeRunEvent($runner, $event);

        $this->assertTrue(true);
    }

    //
    // Group C: plugin schedule registration via HasTaskScheduler
    //

    /**
     * A plugin implementing HasTaskScheduler must have its scheduled task added to
     * the schedule when registerPluginSchedules() runs.
     */
    public function testPluginImplementingHasTaskSchedulerGetsTaskAdded(): void
    {
        // Site context so loadAllPlugins(true)'s context resolution stays harmless.
        $this->mockRequest();

        $plugin = new class () extends GenericPlugin implements HasTaskScheduler {
            public function register($category, $path, $mainContextId = null): bool
            {
                return true;
            }

            public function registerSchedules(PKPScheduler $scheduler): void
            {
                $scheduler
                    ->addSchedule(new SchedulerTestTask())
                    ->daily()
                    ->name(SchedulerTestTask::class);
            }

            public function getName(): string
            {
                return 'schedulerTestPlugin';
            }

            public function getDisplayName(): string
            {
                return 'Scheduler Test Plugin';
            }

            public function getDescription(): string
            {
                return 'Test plugin that registers a scheduled task';
            }
        };

        class_alias($plugin::class, 'APP\\plugins\\generic\\schedulerTestPlugin\\SchedulerTestPluginPlugin');
        PluginRegistry::loadPlugin('generic', 'schedulerTestPlugin');

        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);
        $scheduler->registerPluginSchedules();

        $this->assertContains(
            SchedulerTestTask::class,
            $this->registeredTaskNames($schedule),
            'The HasTaskScheduler plugin task should be registered.'
        );
    }

    /**
     * A plugin that does NOT implement HasTaskScheduler must be skipped, even if it
     * has a registerSchedules() method, so its task must not be added.
     */
    public function testPluginNotImplementingHasTaskSchedulerIsSkipped(): void
    {
        $this->mockRequest();

        // Note: deliberately does NOT implement HasTaskScheduler.
        $plugin = new class () extends GenericPlugin {
            public function register($category, $path, $mainContextId = null): bool
            {
                return true;
            }

            public function registerSchedules(PKPScheduler $scheduler): void
            {
                // Would add a task IF this plugin were a HasTaskScheduler, but it is not,
                // so registerPluginSchedules() must never call this method.
                $scheduler
                    ->addSchedule(new SchedulerTestTask())
                    ->daily()
                    ->name(SchedulerTestTask::class);
            }

            public function getName(): string
            {
                return 'schedulerSkippedPlugin';
            }

            public function getDisplayName(): string
            {
                return 'Scheduler Skipped Plugin';
            }

            public function getDescription(): string
            {
                return 'Test plugin without the HasTaskScheduler interface';
            }
        };

        class_alias($plugin::class, 'APP\\plugins\\generic\\schedulerSkippedPlugin\\SchedulerSkippedPluginPlugin');
        PluginRegistry::loadPlugin('generic', 'schedulerSkippedPlugin');

        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);
        $scheduler->registerPluginSchedules();

        $this->assertNotContains(
            SchedulerTestTask::class,
            $this->registeredTaskNames($schedule),
            'A plugin without HasTaskScheduler must not have its task registered.'
        );
    }

    //
    // Group D: a due task actually runs (web + CLI)
    //

    /**
     * The web based runner must execute a due task.
     */
    public function testDueTaskRunsInWebMode(): void
    {
        $ran = false;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran = true;
        })->everyMinute()->name('test.web.task');

        $runner = new ScheduleTaskRunner(
            $schedule,
            app(Dispatcher::class),
            app(Cache::class),
            app(ExceptionHandler::class)
        );
        $runner->run();

        $this->assertTrue($ran, 'A due task should run via the web based task runner.');
    }

    /**
     * The CLI path (Laravel's ScheduleRunCommand, as tools/scheduler.php run uses)
     * must execute a due task.
     */
    public function testDueTaskRunsInCliMode(): void
    {
        $ran = false;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran = true;
        })->everyMinute()->name('test.cli.task');

        // ScheduleRunCommand::handle() resolves the Schedule from the container via
        // method injection; rebind it to our test schedule so only our task runs
        // (not the container's full core/plugin schedule). Process isolation makes
        // the rebinding safe without restoring.
        app()->instance(Schedule::class, $schedule);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $command = new ScheduleRunCommand();
        $command->setLaravel(PKPContainer::getInstance());
        $command->setInput($input);
        $command->setOutput(new OutputStyle($input, $output));
        $command->run($input, $output);

        $this->assertTrue($ran, 'A due task should run via the CLI ScheduleRunCommand.');
    }

    //
    // Helpers
    //

    /**
     * Invoke the protected ScheduleTaskRunner::runEvent() on the given event.
     */
    private function invokeRunEvent(ScheduleTaskRunner $runner, Event $event): void
    {
        $method = new ReflectionMethod($runner, 'runEvent');
        $method->invoke($runner, $event);
    }

    /**
     * The display summaries (task class names) of every event on the schedule.
     *
     * @return string[]
     */
    private function registeredTaskNames(Schedule $schedule): array
    {
        return array_map(
            fn (Event $event) => $event->getSummaryForDisplay(),
            $schedule->events()
        );
    }
}

/**
 * Minimal ScheduledTask fixture shared by every test. The constructor is intentionally
 * a no-op to avoid the base class's log-directory filesystem setup; the scheduler only
 * reads the object's class name and never executes it during registration. Each test
 * runs in its own process with a fresh Schedule, so a single class is enough.
 */
class SchedulerTestTask extends ScheduledTask
{
    public function __construct()
    {
    }

    protected function executeActions(): bool
    {
        return true;
    }
}
