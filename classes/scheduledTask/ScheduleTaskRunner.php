<?php

/**
 * @file classes/scheduledTask/ScheduleTaskRunner.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduleTaskRunner
 *
 * @brief Web based schedule task runner.
 */

namespace PKP\scheduledTask;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use PKP\config\Config;
use PKP\core\PKPContainer;
use Throwable;

class ScheduleTaskRunner
{
    /**
     * The 24 hour timestamp this scheduler command started running.
     */
    protected Carbon $startedAt;

    /**
     * Constructor
     *
     * @param Schedule $schedule The schedule instance.
     * @param Dispatcher $dispatcher The event dispatcher
     * @param Cache $cache The cache store implementation
     * @param ExceptionHandler $handler The exception handler
     */
    public function __construct(
        protected Schedule $schedule,
        protected Dispatcher $dispatcher,
        protected Cache $cache,
        protected ExceptionHandler $handler
    ) {
        $this->startedAt = Carbon::now();
    }

    /**
     * Run all the due schedule tasks.
     * 
     * As schedule task running in web mode, no tracking possible to know if miss on web request.
     * So use DB store based system to contains the last run mapped to each tasks name which 
     * used to determine possible miss at next web absed task runner . This is as web request
     * just may not fired at specified schedule task run time defined and laravel's system
     * has not mechamism to track it.
     */
    public function run(): void
    {
        $container = PKPContainer::getInstance();
        $now = Carbon::now();
        $interval = (int) Config::getVar('schedule', 'task_runner_interval', 60);

        $lastRunTimes = ScheduledTaskHelper::getLastRunTimes();
        $lastRunTimesChanged = false;

        // Frequent, exactly-due events, collected for the sub-minute repeat handling below.
        $dueFrequentEvents = new Collection();

        foreach ($this->schedule->events() as $event) {
            // which is defined in APP/PKP scheduler via the `name` as displayable task name
            $taskName = $event->getSummaryForDisplay();

            $cron = new CronExpression($event->getExpression());
            $timezone = $event->timezone ?: null;
            $previousBoundary = $cron->getPreviousRunDate($now, 0, true, $timezone)->getTimestamp();
            $nextBoundary = $cron->getNextRunDate($now, 0, false, $timezone)->getTimestamp();

            // Frequent task (e.g. everyMinute): runs on its normal exact-minute schedule (no catch-up),
            // but its last run is still recorded so the store has an entry for every task.
            if (($nextBoundary - $previousBoundary) <= $interval) {
                if (!$event->isDue($container)) {
                    continue;
                }

                if (!$event->filtersPass($container)) {
                    $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));
                    continue;
                }

                $dueFrequentEvents->push($event);

                if ($event->onOneServer) {
                    $this->runSingleServerEvent($event);
                } else {
                    $this->runEvent($event);
                }

                // Record the run. Frequent tasks are not seeded or caught up (their decision stays on
                // isDue() above), but we still store their last run so every task has an entry.
                if (is_string($taskName) && $taskName !== '') {
                    $lastRunTimes[$taskName] = $now->getTimestamp();
                    $lastRunTimesChanged = true;
                }

                continue;
            }

            // Infrequent task (daily/monthly): run it if the current boundary was missed.
            if (!is_string($taskName) || $taskName === '') {
                continue; // no stable identity to track a missed run against
            }

            $lastRun = $lastRunTimes[$taskName] ?? null;

            // First sighting: seed the last-run to the current boundary WITHOUT running, so the task
            // fires from the next boundary onward. Because the store is durable, this seeding happens
            // exactly once
            if ($lastRun === null) {
                $lastRunTimes[$taskName] = $previousBoundary;
                $lastRunTimesChanged = true;

                continue;
            }

            // Already ran for the current boundary.
            if ($lastRun >= $previousBoundary) {
                continue;
            }

            if (!$event->filtersPass($container)) {
                $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));
                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($event);
            } else {
                $this->runEvent($event);
            }

            // Stamp on attempt (not only on success) so a transient failure retries at the next
            // boundary instead of on every tick.
            $lastRunTimes[$taskName] = $now->getTimestamp();
            $lastRunTimesChanged = true;
        }

        if ($lastRunTimesChanged) {
            ScheduledTaskHelper::saveLastRunTimes($lastRunTimes);
        }

        if ($dueFrequentEvents->contains->isRepeatable()) {
            $this->repeatEvents($dueFrequentEvents->filter->isRepeatable());
        }
    }

    /**
     * Run the given single server event.
     */
    protected function runSingleServerEvent(Event $event): void
    {
        if ($this->schedule->serverShouldRun($event, $this->startedAt)) {
            $this->runEvent($event);
        }
    }

    /**
     * Run the given event.
     */
    protected function runEvent(Event $event): void
    {
        $this->dispatcher->dispatch(new ScheduledTaskStarting($event));

        $start = microtime(true);

        try {
            $event->run(PKPContainer::getInstance());

            $this->dispatcher->dispatch(new ScheduledTaskFinished(
                $event,
                round(microtime(true) - $start, 2)
            ));

        } catch (Throwable $e) {

            $this->dispatcher->dispatch(new ScheduledTaskFailed($event, $e));

            $this->handler->report($e);
        }
    }

    /**
     * Run the given repeating events.
     *
     * @param \Illuminate\Support\Collection<\Illuminate\Console\Scheduling\Event>  $events
     */
    protected function repeatEvents(Collection $events): void
    {
        $hasEnteredMaintenanceMode = false;

        while (Carbon::now()->lte($this->startedAt->endOfMinute())) {
            foreach ($events as $event) {
                if ($this->shouldInterrupt()) {
                    return;
                }

                if (!$event->shouldRepeatNow()) {
                    continue;
                }

                $hasEnteredMaintenanceMode = $hasEnteredMaintenanceMode || PKPContainer::getInstance()->isDownForMaintenance();

                if ($hasEnteredMaintenanceMode && !$event->runsInMaintenanceMode()) {
                    continue;
                }

                if (!$event->filtersPass(PKPContainer::getInstance())) {
                    $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));

                    continue;
                }

                if ($event->onOneServer) {
                    $this->runSingleServerEvent($event);
                } else {
                    $this->runEvent($event);
                }
            }

            Sleep::usleep(100000);
        }
    }

    /**
     * Determine if the schedule run should be interrupted.
     */
    protected function shouldInterrupt(): bool
    {
        return $this->cache->get('illuminate:schedule:interrupt', false);
    }

    /**
     * Ensure the interrupt signal is cleared.
     */
    protected function clearInterruptSignal(): bool
    {
        return $this->cache->forget('illuminate:schedule:interrupt');
    }
}
