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

use APP\core\Application;
use APP\scheduler\Scheduler;
use Carbon\Carbon;
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
use PKP\core\ScheduleServiceProvider;
use PKP\db\DAORegistry;
use PKP\plugins\GenericPlugin;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\PKPScheduler;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\scheduledTask\ScheduleTaskRunner;
use PKP\site\Site;
use PKP\site\SiteDAO;
use PKP\tests\PKPTestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPScheduler::class)]
#[CoversClass(Scheduler::class)]
#[CoversClass(ScheduleTaskRunner::class)]
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
     * Snapshot and restore the real SiteDAO around each test
     *
     * @see \PKP\tests\PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return ['SiteDAO'];
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
        $this->useInMemorySiteStore();

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
    // Group E: ScheduleServiceProvider shutdown guard + atomic interval claim
    //

    /**
     * When the request context cannot be resolved (a non-context path such as
     * /aws, a bot probe or a broken link), the web based task runner must bail without
     * throwing and without claiming the interval, so a later valid request still runs it.
     */
    public function testShutdownRunnerBailsAndDoesNotClaimIntervalOnUnresolvableContext(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');

        // A first path segment that is not a real journal makes PKPRouter::getContext()
        // throw NotFoundHttpException, reproducing the #12833 trigger.
        $this->mockRequest('nonexistentContext12833/index/index');

        // Precondition: the chosen path really does make getContext() throw.
        $threw = false;
        try {
            Application::get()->getRequest()->getContext();
        } catch (\Throwable $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Test setup: the chosen path must make getContext() throw.');

        $provider = new ScheduleServiceProvider(PKPContainer::getInstance());

        // The shutdown runner must swallow the throw (no fatal at request shutdown)...
        $this->invokeShutdownRunner($provider, time(), 60);

        // ...and must NOT have claimed the interval, so the next valid request can still run.
        $this->assertNull(
            \Illuminate\Support\Facades\Cache::get('schedule::taskRunner::lastRunAt'),
            'An unresolvable request context must not consume the task runner interval.'
        );
    }

    /**
     * The interval claim must be atomic and once-per-interval: the first caller wins and
     * any concurrent caller within the interval is rejected. This is the guard that keeps
     * a burst of requests after an interval boundary from each spinning up a task runner.
     */
    public function testIntervalClaimIsAtomicOncePerInterval(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');

        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', time(), 60),
            'The first request in an interval must win the claim.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', time(), 60),
            'A concurrent request within the same interval must lose the claim.'
        );
    }

    /**
     * task_runner_interval = 0 ("run on every request") must still work: Cache::add() treats
     * a <= 0 TTL as "do not store" and returns false, so the runner floors the TTL at 1 second.
     */
    public function testIntervalClaimTtlIsFlooredForZeroInterval(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', time(), 0),
            'A zero TTL must not store (documents why the runner floors the interval).'
        );

        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');
        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', time(), max(1, 0)),
            'Flooring the TTL at 1 second keeps the runner working when the interval is 0.'
        );
    }

    /**
     * With a resolvable request context (site context here), the runner must pass the context
     * guard, atomically claim the interval, and invoke the scheduler. The CLI console guard is
     * bypassed via a proxied partial-mock container whose runningInConsole() returns false, so
     * the production code is exercised unchanged.
     */
    public function testShutdownRunnerClaimsIntervalAndRunsOnResolvableContext(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');

        // Site context: getContext() returns null (no throw), so the context guard passes.
        $this->mockRequest();

        $scheduler = Mockery::mock(Scheduler::class);
        $scheduler->shouldReceive('registerPluginSchedules')->once();
        $scheduler->shouldReceive('runWebBasedScheduleTaskRunner')->once();
        app()->instance(Scheduler::class, $scheduler);

        // Proxied partial mock: forwards everything to the real container except the CLI guard.
        $app = Mockery::mock(PKPContainer::getInstance());
        $app->shouldReceive('runningInConsole')->andReturn(false);

        $provider = new ScheduleServiceProvider($app);
        $this->invokeShutdownRunner($provider, time(), 60);

        $this->assertNotNull(
            \Illuminate\Support\Facades\Cache::get('schedule::taskRunner::lastRunAt'),
            'A resolvable context must claim the interval and run.'
        );
    }

    /**
     * Anything that throws while registering/running schedules at request shutdown must be
     * swallowed (logged, not fatal), since the response has already been flushed.
     */
    public function testShutdownRunnerSwallowsRunnerFailure(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');

        $this->mockRequest();

        $scheduler = Mockery::mock(Scheduler::class);
        $scheduler->shouldReceive('registerPluginSchedules')->andThrow(new Exception('boom'));
        $scheduler->shouldReceive('runWebBasedScheduleTaskRunner')->never();
        app()->instance(Scheduler::class, $scheduler);

        $app = Mockery::mock(PKPContainer::getInstance());
        $app->shouldReceive('runningInConsole')->andReturn(false);

        $provider = new ScheduleServiceProvider($app);

        // Must not propagate the throwable out of the shutdown handler.
        $this->invokeShutdownRunner($provider, time(), 60);

        $this->assertTrue(true);
    }

    /**
     * The interval claim must self-expire after the interval so the next cycle can re-claim it,
     * giving the once-per-interval cadence. FileStore reads time via Carbon::now(), so the expiry
     * is driven deterministically with Carbon::setTestNow().
     */
    public function testIntervalClaimExpiresAndIsReclaimableNextInterval(): void
    {
        \Illuminate\Support\Facades\Cache::forget('schedule::taskRunner::lastRunAt');

        $start = Carbon::create(2026, 1, 1, 0, 0, 0);
        Carbon::setTestNow($start);

        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', $start->timestamp, 60),
            'The first claim within a fresh interval must win.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', $start->timestamp, 60),
            'A second claim within the same interval must lose.'
        );
        $this->assertSame(
            $start->timestamp,
            \Illuminate\Support\Facades\Cache::get('schedule::taskRunner::lastRunAt'),
            'The stored timestamp must be readable while the claim is live.'
        );

        // Advance past the interval: the claim expires, the key reads as null, and it is re-claimable.
        Carbon::setTestNow($start->copy()->addSeconds(61));
        $this->assertNull(
            \Illuminate\Support\Facades\Cache::get('schedule::taskRunner::lastRunAt'),
            'An expired claim must read as null.'
        );
        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::add('schedule::taskRunner::lastRunAt', Carbon::now()->timestamp, 60),
            'After expiry the next interval must be re-claimable.'
        );

        Carbon::setTestNow();
    }

    //
    // Group F: web based runner catch-up for infrequent (hourly/daily/monthly) tasks
    //

    /**
     * A daily task whose exact 00:00 boundary was missed (no web request fired the runner in that
     * minute) must run on the next tick that finds it overdue, then not run again the same day.
     */
    public function testMissedDailyTaskCatchesUpOnNextTickThenStops(): void
    {
        $this->useInMemorySiteStore();

        // 09:00 — hours past today's 00:00 boundary; the exact minute was missed.
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 0, 0));

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->daily()->name('test.catchup.daily');

        // A recorded run from *yesterday* (not first-sighting), so today's boundary is genuinely missed.
        ScheduledTaskHelper::saveLastRunTimes(['test.catchup.daily' => Carbon::create(2026, 1, 14, 0, 0, 0)->timestamp]);

        $this->makeRunner($schedule)->run();
        $this->assertSame(1, $ran, 'A daily task whose 00:00 boundary was missed must catch up on the next tick.');

        // A later tick the same day must not re-run it.
        $this->makeRunner($schedule)->run();
        $this->assertSame(1, $ran, 'The daily task must run only once per day.');

        Carbon::setTestNow();
    }

    /**
     * The first time the runner sees an infrequent task (no stored last-run) it must SEED the
     * last-run to the current boundary WITHOUT running — so it fires from the next boundary onward,
     * never in an unexpected burst on deploy.
     */
    public function testFirstSightingSeedsWithoutRunning(): void
    {
        $this->useInMemorySiteStore();

        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 0, 0));

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->daily()->name('test.seed.daily');

        $this->makeRunner($schedule)->run();

        $this->assertSame(0, $ran, 'On first sighting a daily task must be seeded, not run.');
        $this->assertSame(
            Carbon::create(2026, 1, 15, 0, 0, 0)->timestamp,
            ScheduledTaskHelper::getLastRunTimes()['test.seed.daily'] ?? null,
            'First sighting must seed the last-run to the current daily boundary (today 00:00).'
        );

        Carbon::setTestNow();
    }

    /**
     * A ->monthlyOn(10) task must not run before the 10th, must catch up on the first tick on/after
     * the 10th, and must run only once that month.
     */
    public function testMissedMonthlyTaskCatchesUpAfterItsBoundary(): void
    {
        $this->useInMemorySiteStore();

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->monthlyOn(10)->name('test.catchup.monthly');

        // Last ran on last month's 10th.
        ScheduledTaskHelper::saveLastRunTimes(['test.catchup.monthly' => Carbon::create(2025, 12, 10, 0, 0, 0)->timestamp]);

        // Jan 5 — before this month's boundary (the 10th): must not run.
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 12, 0, 0));
        $this->makeRunner($schedule)->run();
        $this->assertSame(0, $ran, 'Before the monthly boundary (the 10th) the task must not run.');

        // Jan 12 — on/after the boundary: must catch up once.
        Carbon::setTestNow(Carbon::create(2026, 1, 12, 8, 0, 0));
        $this->makeRunner($schedule)->run();
        $this->assertSame(1, $ran, 'On/after the monthly boundary the task must catch up.');

        // Jan 20 — same month, later tick: must not run again.
        Carbon::setTestNow(Carbon::create(2026, 1, 20, 8, 0, 0));
        $this->makeRunner($schedule)->run();
        $this->assertSame(1, $ran, 'A monthly task must run only once per month.');

        Carbon::setTestNow();
    }

    /**
     * A multi-cycle outage must fire the task exactly once (fire-once misfire policy), not once per
     * missed cycle — e.g. five dark days do not produce five daily runs.
     */
    public function testMultiCycleOutageRunsOnce(): void
    {
        $this->useInMemorySiteStore();

        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 0, 0));

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->daily()->name('test.outage.daily');

        // Last ran 5 days ago; the site was dark since.
        ScheduledTaskHelper::saveLastRunTimes(['test.outage.daily' => Carbon::create(2026, 1, 10, 0, 0, 0)->timestamp]);

        $this->makeRunner($schedule)->run();

        $this->assertSame(1, $ran, 'A multi-day outage must run the daily task once, not once per missed day.');

        Carbon::setTestNow();
    }

    /**
     * A frequent (->everyMinute) task keeps the exact-minute behavior — runs when due — and its last
     * run IS recorded (stamped at run time) so the store has an entry for every task.
     */
    public function testEveryMinuteTaskRunsAndIsRecorded(): void
    {
        $this->useInMemorySiteStore();

        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 30, 0));

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->everyMinute()->name('test.frequent.minute');

        $this->makeRunner($schedule)->run();

        $this->assertSame(1, $ran, 'An everyMinute task must run on the tick.');
        $this->assertSame(
            Carbon::create(2026, 1, 15, 9, 30, 0)->timestamp,
            ScheduledTaskHelper::getLastRunTimes()['test.frequent.minute'] ?? null,
            'A frequent task must record its last run (stamped at run time).'
        );

        Carbon::setTestNow();
    }

    /**
     * A frequent task is decided by isDue(), not by the stored last-run: even with a last-run already
     * recorded for the current minute (which would make an *infrequent* task skip), it must still run.
     * This proves the miss/hit catch-up is not applied to frequent tasks.
     */
    public function testFrequentTaskRunsEveryTickIgnoringStoredLastRun(): void
    {
        $this->useInMemorySiteStore();

        // 09:30:30 — half a minute into the 09:30 boundary; a stored last-run at 09:30:00 would make an
        // infrequent task "already ran this boundary" and skip.
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 30, 30));
        ScheduledTaskHelper::saveLastRunTimes(['test.frequent.decision' => Carbon::create(2026, 1, 15, 9, 30, 0)->timestamp]);

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        })->everyMinute()->name('test.frequent.decision');

        $this->makeRunner($schedule)->run();

        $this->assertSame(1, $ran, 'A frequent task must run every tick (isDue), regardless of its stored last-run.');
        $this->assertSame(
            Carbon::create(2026, 1, 15, 9, 30, 30)->timestamp,
            ScheduledTaskHelper::getLastRunTimes()['test.frequent.decision'] ?? null,
            'Running the frequent task must refresh its recorded last run to the current tick.'
        );

        Carbon::setTestNow();
    }

    //
    // Helpers
    //

    /**
     * Build a web based task runner over the given schedule using the container's real services.
     */
    private function makeRunner(Schedule $schedule): ScheduleTaskRunner
    {
        return new ScheduleTaskRunner(
            $schedule,
            app(Dispatcher::class),
            app(Cache::class),
            app(ExceptionHandler::class)
        );
    }

    /**
     * Install an in-memory Site store for the catch-up tests: a mock SiteDAO whose getSite() returns
     * a fresh, empty Site and whose updateObject() is safe on main DB store.
     */
    private function useInMemorySiteStore(): Site
    {
        $site = new Site();

        $siteDao = Mockery::mock(SiteDAO::class);
        $siteDao->shouldReceive('getSite')->andReturn($site);
        $siteDao->shouldReceive('updateObject')->andReturnNull();
        DAORegistry::registerDAO('SiteDAO', $siteDao);

        return $site;
    }

    /**
     * Invoke the protected ScheduleTaskRunner::runEvent() on the given event.
     */
    private function invokeRunEvent(ScheduleTaskRunner $runner, Event $event): void
    {
        $method = new ReflectionMethod($runner, 'runEvent');
        $method->invoke($runner, $event);
    }

    /**
     * Invoke the protected ScheduleServiceProvider shutdown runner directly.
     */
    private function invokeShutdownRunner(ScheduleServiceProvider $provider, int $currentTimestamp, int $taskRunnerInterval): void
    {
        $method = new ReflectionMethod($provider, 'runWebBasedScheduleTaskRunnerOnShutdown');
        $method->invoke($provider, $currentTimestamp, $taskRunnerInterval);
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
