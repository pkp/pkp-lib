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
 * @see \PKP\core\ScheduleServiceProvider
 * @see \APP\scheduler\Scheduler
 *
 * @brief Tests for the custom PKP wiring around Laravel's scheduler:
 *  - addSchedule() dedup and default naming contract
 *  - plugin schedule registration via the HasTaskScheduler interface
 *  - a due task actually running via the CLI
 *  - application timezone resolution
 *  - task filters being honoured before a task runs
 */

namespace PKP\tests\classes\scheduledTask;

use APP\core\Application;
use APP\scheduler\Scheduler;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\config\Config;
use PKP\core\PKPContainer;
use PKP\core\Registry;
use PKP\core\ScheduleServiceProvider;
use PKP\plugins\GenericPlugin;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\PKPScheduler;
use PKP\scheduledTask\ScheduledTask;
use PKP\tests\PKPTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPScheduler::class)]
#[CoversClass(Scheduler::class)]
#[CoversClass(ScheduleServiceProvider::class)]
class SchedulerTest extends PKPTestCase
{
    /** @var resource Temp file backing error_log for the duration of each test. */
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();
        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        // The plugin registry is process-global; clear it so nothing leaks even
        // though each test method already runs in its own process.
        Registry::delete('plugins');

        parent::tearDown();
    }

    /**
     * Snapshot and restore the parsed config so the timezone tests can rewrite [general]
     *
     * @see \PKP\tests\PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'configData'];
    }

    //
    // Group A: PKPScheduler::addSchedule() dedup and default naming contract
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

    /**
     * A newly created event must carry the task's class name as its event name. Without it
     * getSummaryForDisplay() falls back to the literal 'Callback', which every unnamed task
     * would share as an identity.
     */
    public function testAddScheduleNamesNewEventWithTaskClass(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        $event = $scheduler->addSchedule(new SchedulerTestTask());

        $this->assertSame(SchedulerTestTask::class, $event->getSummaryForDisplay());
    }

    /**
     * Registering the same task twice must reuse the first event. This only works because
     * addSchedule() names the event it creates: the dedup map is keyed by
     * getSummaryForDisplay() but looked up by class name, so an unnamed event can never match.
     */
    public function testAddScheduleDedupsRepeatedRegistrationOfSameTask(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        $first = $scheduler->addSchedule(new SchedulerTestTask());
        $countAfterFirst = count($schedule->events());

        $second = $scheduler->addSchedule(new SchedulerTestTask());

        $this->assertSame($first, $second);
        $this->assertCount($countAfterFirst, $schedule->events());
    }

    /**
     * The class name is only a default. A caller that chains its own ->name() must still win,
     * exactly as plugins do today in their registerSchedules() implementations.
     */
    public function testAddScheduleDefaultNameDoesNotOverrideCallerName(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        $event = $scheduler
            ->addSchedule(new SchedulerTestTask())
            ->daily()
            ->name('my.custom.task.name');

        $this->assertSame('my.custom.task.name', $event->getSummaryForDisplay());
    }

    /**
     * Naming the event up front also removes the ordering trap: Laravel's CallbackEvent throws
     * a LogicException when withoutOverlapping() is called before a name is set, so a caller
     * that chains them in that order used to crash at registration.
     */
    public function testAddScheduleAllowsWithoutOverlappingBeforeName(): void
    {
        $schedule = new Schedule();
        $scheduler = new Scheduler($schedule);

        $event = $scheduler
            ->addSchedule(new SchedulerTestTask())
            ->daily()
            ->withoutOverlapping();

        $this->assertSame(SchedulerTestTask::class, $event->getSummaryForDisplay());
    }

    //
    // Group B: plugin schedule registration via HasTaskScheduler
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
    // Group C: a due task actually runs via the CLI
    //

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

        $this->runScheduleViaCli($schedule);

        $this->assertTrue($ran, 'A due task should run via the CLI ScheduleRunCommand.');
    }

    //
    // Group D: application timezone resolution
    //

    /**
     * Scheduled events must be evaluated in the application's timezone.
     */
    public function testScheduleUsesResolvedApplicationTimezone(): void
    {
        $originalTimezone = date_default_timezone_get();

        try {
            // Stand in for PKPApplication::initializeTimeZone() having resolved [general] time_zone
            date_default_timezone_set('America/Vancouver');

            $app = PKPContainer::getInstance();
            (new ScheduleServiceProvider($app))->register();

            // Invoke the registered binding directly rather than resolving through the container:
            // resolving fires the boot() afterResolving hook, which registers every core and plugin
            // schedule and needs a full request context that this test has no reason to build.
            $concrete = $app->getBindings()[Schedule::class]['concrete'];
            $schedule = $concrete($app); /** @var Schedule $schedule */

            $event = $schedule->call(fn () => null)->daily();

            $this->assertSame(
                'America/Vancouver',
                (string) $event->timezone,
                'Scheduled events must inherit the application timezone, not a hardcoded UTC fallback.'
            );
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    /**
     * A legacy [general] time_zone value must resolve to a canonical identifier.
     */
    public function testResolveTimeZoneMapsLegacyNameToCanonicalIdentifier(): void
    {
        $this->setConfigTimeZone('Amsterdam');

        $resolved = Application::resolveTimeZone();

        $this->assertSame('Europe/Amsterdam', $resolved);

        // The point of resolving at all: the result must be usable as a real zone.
        $this->assertSame('Europe/Amsterdam', (new \DateTimeZone($resolved))->getName());
    }

    /**
     * A value that is already canonical must survive untouched.
     */
    public function testResolveTimeZoneKeepsCanonicalIdentifier(): void
    {
        $this->setConfigTimeZone('America/Vancouver');

        $this->assertSame('America/Vancouver', Application::resolveTimeZone());
    }

    /**
     * An unrecognisable value must fall back rather than propagate or throw, so a typo in the
     * config cannot take the whole application down at boot.
     */
    public function testResolveTimeZoneFallsBackOnUnrecognisableValue(): void
    {
        $this->setConfigTimeZone('Not/A_Real Zone');

        $resolved = Application::resolveTimeZone();

        $this->assertNotSame('Not/A_Real Zone', $resolved);
        $this->assertSame($resolved, (new \DateTimeZone($resolved))->getName());
    }

    //
    // Group E: task filters (->when()/->skip()) are honoured before the task is constructed
    //

    /**
     * Registration must be indifferent to a filter: a task with one is registered exactly like a task
     * without, keeping its identity and its schedule. A filter decides whether a task runs, never
     * whether it is known to the scheduler -- which is what keeps it visible in the scheduler listing.
     */
    public function testRegistrationIsUnaffectedByAFilter(): void
    {
        $schedule = new Schedule();

        $plain = $schedule->call(fn () => null)->daily()->name('test.filter.absent');
        $filtered = $schedule->call(fn () => null)->daily()->name('test.filter.present')->when(fn () => false);

        $this->assertCount(2, $schedule->events(), 'A filtered task must still be registered.');
        $this->assertSame(
            ['test.filter.absent', 'test.filter.present'],
            $this->registeredTaskNames($schedule),
            'A filtered task must keep its own identity in the schedule.'
        );
        $this->assertSame(
            $plain->getExpression(),
            $filtered->getExpression(),
            'A filter must not alter the task\'s schedule.'
        );
    }

    /**
     * The filter is consulted, not assumed: a task admitted by its filter must still run. Without
     * this the rejection test below would also pass on a scheduler that refused everything.
     */
    public function testTaskWithAPassingFilterStillRuns(): void
    {
        $ran = 0;
        $schedule = new Schedule();
        $schedule
            ->call(function () use (&$ran) {
                $ran++;
            })
            ->everyMinute()
            ->name('test.filter.passes')
            ->when(fn () => true);

        $this->runScheduleViaCli($schedule);

        $this->assertSame(1, $ran, 'A task whose filter passes must run normally.');
    }

    /**
     * The filter is evaluated when the scheduler runs, not when the task was registered, so a
     * condition that changes at runtime -- an admin editing config.inc.php, say -- takes effect
     * without the schedule being rebuilt.
     */
    public function testFilterIsEvaluatedPerRunNotAtRegistration(): void
    {
        $allowed = false;
        $schedule = new Schedule();
        $event = $schedule
            ->call(fn () => null)
            ->everyMinute()
            ->name('test.filter.latebound')
            // By reference on purpose: an arrow function would capture $allowed by value at
            // registration, which is exactly the late binding this test is here to disprove.
            ->when(function () use (&$allowed) {
                return $allowed;
            });

        $container = PKPContainer::getInstance();

        $this->assertFalse($event->filtersPass($container), 'The filter must reject while the condition is false.');

        $allowed = true;

        $this->assertTrue(
            $event->filtersPass($container),
            'The same event must be admitted once the condition flips, without re-registration.'
        );
    }

    /**
     * A due task whose filter rejects it must not run.
     */
    public function testFilteredTaskDoesNotRun(): void
    {
        $ran = 0;
        $schedule = new Schedule();
        $schedule
            ->call(function () use (&$ran) {
                $ran++;
            })
            ->everyMinute()
            ->name('test.filtered.cli')
            ->when(fn () => false);

        $this->runScheduleViaCli($schedule);

        $this->assertSame(0, $ran, 'A filtered task must not run even though it is due.');
    }

    //
    // Helpers
    //

    /**
     * Run the given schedule through Laravel's ScheduleRunCommand, as tools/scheduler.php run does.
     *
     * ScheduleRunCommand::handle() resolves the Schedule from the container via method injection;
     * rebind it to the given schedule so only its tasks run (not the container's full core/plugin
     * schedule). Process isolation makes the rebinding safe without restoring.
     */
    private function runScheduleViaCli(Schedule $schedule): void
    {
        app()->instance(Schedule::class, $schedule);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $command = new ScheduleRunCommand();
        $command->setLaravel(PKPContainer::getInstance());
        $command->setInput($input);
        $command->setOutput(new OutputStyle($input, $output));
        $command->run($input, $output);
    }

    /**
     * Rewrite [general] time_zone in the in-memory config for the duration of a test.
     *
     * Goes through Config::getData() rather than the registry directly: it parses config.inc.php
     * on first access, so the override lands on top of the real config instead of replacing it
     * with a bare array (which would leave [general] installed unset, and so put the whole
     * application into maintenance mode for the rest of the process).
     */
    private function setConfigTimeZone(string $timeZone): void
    {
        $configData = & Config::getData();
        $configData['general']['time_zone'] = $timeZone;
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
