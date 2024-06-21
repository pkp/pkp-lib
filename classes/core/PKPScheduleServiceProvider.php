<?php

namespace PKP\core;

use PKP\config\Config;
use PKP\core\PKPContainer;
use PKP\scheduledTask\PKPSchedular;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class PKPScheduleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            (new PKPSchedular($schedule))->registerSchedules();
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

        $this->app->alias(Schedule::class, 'schedule');
    }
}