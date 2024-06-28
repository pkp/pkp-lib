<?php

namespace PKP\core;

use PKP\config\Config;
use APP\core\Application;
use PKP\core\PKPContainer;
use APP\scheduler\Scheduler;
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

        // MUST DO : Need to add a check if schedule_task_runner enable
        register_shutdown_function(function () {
            // As this runs at the current request's end but the 'register_shutdown_function' registered
            // at the service provider's registration time at application initial bootstrapping,
            // need to check the maintenance status within the 'register_shutdown_function'
            if (Application::get()->isUnderMaintenance()) {
                return;
            }

            // Application is set to sandbox mode and will not run any schedule tasks
            if (Config::getVar('general', 'sandbox', false)) {
                error_log('Application is set to sandbox mode and will not run any schedule tasks');
                return false;
            }

            // We only want to web based task runner for the web request life cycle
            // not in any CLI based request life cycle
            if (runOnCLI()) {
                return;
            }

            // MUST DO : Need to calculate the last run time of schedule task runner and calculate if ready to run

            // $scheduler = $this->app->get(Scheduler::class); /** @var \APP\scheduler\Scheduler $scheduler */
            // $scheduler->runWebBasedScheduleTaskRunner();
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