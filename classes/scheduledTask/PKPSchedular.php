<?php

namespace PKP\scheduledTask;

use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\task\RemoveFailedJobs;
use Illuminate\Console\Scheduling\Schedule;

class PKPSchedular
{
    protected Schedule $schedule;

    protected string $appName;

    public function __construct(Schedule $schedule, string $appName = null)
    {
        $this->schedule = $schedule;
        $this->appName = $appName ?? Application::get()->getName();
    }

    public function registerSchedules(): void
    {
        /** @var \PKP\scheduledTask\ScheduledTaskDAO $scheduledTaskDao */
        $scheduledTaskDao = DAORegistry::getDAO('ScheduledTaskDAO');

        $this
            ->schedule
            ->call(fn () => (new RemoveFailedJobs)->execute())
            ->daily()
            ->name(RemoveFailedJobs::class)
            ->withoutOverlapping()
            ->then(fn () => $scheduledTaskDao->updateLastRunTime(RemoveFailedJobs::class));

        // app()->instance(Schedule::class, $this->schedule);
    }
}