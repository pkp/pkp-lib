<?php

/**
 * @file classes/scheduledTask/PKPScheduler.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPScheduler
 *
 * @brief Scheduler class which is responsible to register scheduled tasks
 */

namespace PKP\scheduledTask;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PKP\core\PKPContainer;
use PKP\task\UpdateIPGeoDB;
use PKP\task\ProcessQueueJobs;
use PKP\task\RemoveFailedJobs;
use PKP\task\StatisticsReport;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\ScheduledTask;
use PKP\task\RemoveExpiredInvitations;
use PKP\scheduledTask\ScheduleTaskRunner;
use PKP\task\RemoveUnvalidatedExpiredUsers;
use PKP\plugins\interfaces\HasTaskScheduler;

abstract class PKPScheduler
{
    /**
     * Constructor
     * 
     * @param Schedule $schedule The core illuminate Schedule that is responsible to run schedule tasks
     */
    public function __construct(protected Schedule $schedule)
    {
    }

    /**
     * Add a new schedule task into the core illuminate Schedule
     * 
     * This method allow dynamic injection of schedule tasks at run time. One
     * particular use is allow plugin to add own schedule tasks.
     */
    public function addSchedule(ScheduledTask $scheduleTask): Event
    {
        $events = $this->schedule->events();

        $scheduleTasks = collect($events)->flatMap(
            fn (Event $event) => [$event->getSummaryForDisplay() => $event]
        );

        $scheduleTaskClass = $scheduleTask::class;

        // Here we don't want to re-register the schedule task if it's already registered
        // otherwise the same task might run multiple times at the same time
        return $scheduleTasks[$scheduleTaskClass]
            ?? $this->schedule->call(fn () => $scheduleTask->execute());
    }

    /**
     * Register core schedule tasks
     */
    public function registerSchedules(): void
    {   
        $this
            ->schedule
            ->call(fn () => (new StatisticsReport)->execute())
            ->daily()
            ->name(StatisticsReport::class)
            ->withoutOverlapping();
            
        $this
            ->schedule
            ->call(fn () => (new RemoveUnvalidatedExpiredUsers)->execute())
            ->daily()
            ->name(RemoveUnvalidatedExpiredUsers::class)
            ->withoutOverlapping();
        
        $this
            ->schedule
            ->call(fn () => (new UpdateIPGeoDB)->execute())
            ->cron('0 0 1,10,20 * *')
            ->name(UpdateIPGeoDB::class)
            ->withoutOverlapping();

        $this
            ->schedule
            ->call(fn () => (new ProcessQueueJobs)->execute())
            ->everyMinute()
            ->name(ProcessQueueJobs::class)
            ->withoutOverlapping();

        $this
            ->schedule
            ->call(fn () => (new RemoveFailedJobs)->execute())
            ->daily()
            ->name(RemoveFailedJobs::class)
            ->withoutOverlapping();
        
        $this
            ->schedule
            ->call(fn () => (new RemoveExpiredInvitations)->execute())
            ->daily()
            ->name(RemoveExpiredInvitations::class)
            ->withoutOverlapping();
        
        // We only load all plugins and register their scheduled tasks when running under the CLI
        // On the web based task runner the scheduled tasks must be registered before it starts running
        if (PKPContainer::getInstance()->runningInConsole()) {
            $this->registerPluginSchedules();
        }
    }

    /**
     * Load all plugins and register their scheduled tasks
     */
    public function registerPluginSchedules(): void
    {
        $plugins = PluginRegistry::loadAllPlugins(true);

        foreach ($plugins as $plugin) {
            if (!$plugin instanceof HasTaskScheduler) {
                continue;
            }

            $plugin->registerSchedules($this);
        }
    }

    /**
     * Run the web based schedule task runner
     */
    public function runWebBasedScheduleTaskRunner(): void
    {
        $container = PKPContainer::getInstance();

        (new ScheduleTaskRunner(
            $this->schedule,
            $container->get(\Illuminate\Contracts\Events\Dispatcher::class),
            $container->get(\Illuminate\Contracts\Cache\Repository::class),
            $container->get(\Illuminate\Contracts\Debug\ExceptionHandler::class)
        ))->run();
    }
}
