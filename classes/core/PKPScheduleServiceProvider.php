<?php

namespace PKP\core;

use Carbon\Carbon;
use PKP\config\Config;
use APP\core\Application;
use PKP\core\PKPContainer;
use APP\scheduler\Scheduler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;

class PKPScheduleServiceProvider extends ServiceProvider implements DeferrableProvider
{
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

            if (Config::getVar('schedule', 'task_runner', true)) {
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
                        return;
                    }
    
                    // We only want to web based task runner for the web request life cycle
                    // not in any CLI based request life cycle
                    if (runOnCLI()) {
                        return;
                    }
    
                    $taskRunnerInternal = Config::getVar('schedule', 'task_runner_interval', 60);
                    $lastRunTimestamp   = Cache::get('schedule::taskRunner::lastRunAt') ?? 0;
                    $curerntTimestamp   = Carbon::now()->timestamp;
    
                    // If the last run exceeds task runner interval in secods
                    if ($curerntTimestamp - $lastRunTimestamp > $taskRunnerInternal) {
                        $scheduler = $this->app->get(Scheduler::class); /** @var \APP\scheduler\Scheduler $scheduler */
                        $scheduler->runWebBasedScheduleTaskRunner();
    
                        // Update the last run timestamp
                        Cache::put('schedule::taskRunner::lastRunAt', Carbon::now()->timestamp);
                    }
                });
            }
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

        $this->app->singleton(
            Scheduler::class,
            function ($app) {
                return new Scheduler($app->get(Schedule::class));
            }
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
