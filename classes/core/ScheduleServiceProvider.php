<?php

/**
 * @file classes/core/ScheduleServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduleServiceProvider
 *
 * @brief Register schedule and run related functionalities
 */

namespace PKP\core;

use APP\core\Application;
use APP\scheduler\Scheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot service provider
     */
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {

            // After the resolving to \Illuminate\Console\Scheduling\Schedule::class
            // need to register all the schedules into the scheduler
            $scheduler = $this->app->get(Scheduler::class); /** @var \APP\scheduler\Scheduler $scheduler */

            // No schedule should be registerd in maintenance mode
            if (!Application::get()->isUnderMaintenance()) {
                $scheduler->registerSchedules();
            }
        });
    }

    /**
     * Register service provider
     */
    public function register()
    {
        // initialize schedule
        $this->app->singleton(Schedule::class, function (PKPContainer $app): Schedule {
            $config = $app->get('config'); /** @var \Illuminate\Config\Repository $config */
            $cacheConfig = $config->get('cache'); /** @var array $cacheConfig */

            // The application timezone is resolved once by PKPApplication::initializeTimeZone(),
            // which reads [general] time_zone and maps legacy names to their canonical identifier.
            // This binding is resolved lazily, well after that has run, so the resolved
            // value is both available and the only form safe to hand to DateTime.
            return (
                new Schedule(
                    date_default_timezone_get()
                )
            )->useCache($cacheConfig['default']);
        });

        $this->app->singleton(
            Scheduler::class,
            fn ($app) => new Scheduler($app->get(Schedule::class))
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Schedule::class,
            Scheduler::class,
        ];
    }
}
