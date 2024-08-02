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

use Throwable;
use Carbon\Carbon;
use PKP\core\PKPContainer;
use Illuminate\Support\Sleep;
use Illuminate\Support\Collection;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

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
    )
    {
        $this->startedAt = Carbon::now();
    }

    /**
     * Run all the due schedule tasks
     */
    public function run(): void
    {
        $events = $this->schedule->dueEvents(PKPContainer::getInstance());

        foreach ($events as $event) {
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

        if ($events->contains->isRepeatable()) {
            $this->repeatEvents($events->filter->isRepeatable());
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
