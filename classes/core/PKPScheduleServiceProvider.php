<?php

namespace PKP\core;

use APP\scheduler\Scheduler;
use PKP\config\Config;
use PKP\core\PKPContainer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class PKPScheduleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // After the resolving to \Illuminate\Console\Scheduling\Schedule::class
        // need to register all the schedules into the scheduler
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $scheduler = $this->app->get(Scheduler::class); /** @var \APP\schedular\Scheduler $scheduler */
            $scheduler->registerSchedules();
        });
    }

    public function register()
    {
        // initialize schedule
        $this->app->singleton(Schedule::class, function (PKPContainer $app): Schedule {
            $config = $app->get('config'); /** @var \Illuminate\Config\Repository $config */
            $cacheConfig = $config->get('cache'); /** @var array $cacheConfig */

            return (
                new Schedule(
                    Config::getVar('general', 'timezone', 'UTC')
                )
            )->useCache($cacheConfig['default']);
        });

        $this->app->singleton(Scheduler::class, fn ($app) => new Scheduler($app->get(Schedule::class)));
    }
}