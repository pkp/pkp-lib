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
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use PKP\config\Config;
use Throwable;

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

        if (Config::getVar('schedule', 'task_runner', true)) {
            $taskRunnerInterval = Config::getVar('schedule', 'task_runner_interval', 60);
            $lastRunTimestamp = Cache::get('schedule::taskRunner::lastRunAt') ?? 0;
            $currentTimestamp = Carbon::now()->timestamp;

            if ($currentTimestamp - $lastRunTimestamp > $taskRunnerInterval) {

                if (!$this->app->runningInConsole()) {
                    $this->booted(fn () => $this->app->make(Schedule::class));
                }

                $currentWorkingDir = getcwd();

                register_shutdown_function(function () use ($currentWorkingDir, $currentTimestamp, $taskRunnerInterval) {

                    // restore the current working directory
                    // see: https://www.php.net/manual/en/function.register-shutdown-function.php#refsect1-function.register-shutdown-function-notes
                    chdir($currentWorkingDir);

                    $this->runWebBasedScheduleTaskRunnerOnShutdown($currentTimestamp, $taskRunnerInterval);
                });
            }
        }
    }

    /**
     * Run the web based schedule task runner at the end of the current request.
     *
     * Invoked from the register_shutdown_function() registered in boot(); extracted as a
     * method so the #12833 guard (unresolved request context) and the atomic interval claim
     * can be exercised by tests.
     *
     * @param int $currentTimestamp   Timestamp captured when the request booted.
     * @param int $taskRunnerInterval Configured [schedule] task_runner_interval, in seconds.
     */
    protected function runWebBasedScheduleTaskRunnerOnShutdown(int $currentTimestamp, int $taskRunnerInterval): void
    {
        // Resolve the request context first. On a invalid path (e.g. non context or site level)
        // PKPRouter::getContext() throws a NotFoundHttpException, e.g. the web task runner
        // must not be initiated
        try {
            Application::get()->getRequest()->getContext();
        } catch (Throwable $e) {
            return;
        }

        // do not run any task via task runner when app is under maintenance
        // if needed, that should be manually and explicitly invoked/initiated
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
        if ($this->app->runningInConsole()) {
            return;
        }

        // this is conditional and atomic write which stores the value and returns `true` ONLY IF the key
        // doesn't already exist (or is expired). If the key is already live, it writes nothing and returns
        // false. so exactly one of any concurrent requests after an interval boundary wins and the rest
        // return from here, e.g. preventing a thundering herd of task runners.
        // NOTE: `$ttl` must be above 0 as 0 treats as `do not store` will result in task
        // runner disabled entirely
        if (!Cache::add('schedule::taskRunner::lastRunAt', $currentTimestamp, max(1, $taskRunnerInterval))) {
            return;
        }

        // Everything below runs after the response is flushed, so any failure here is pure log
        // noise and must never surface as a fatal to the already-served client.
        try {
            $scheduler = $this->app->get(Scheduler::class); /** @var \APP\scheduler\Scheduler $scheduler */

            $scheduler->registerPluginSchedules();

            // Flush the output buffer to send the response to the client before
            // running scheduled tasks, preventing page load delays.
            // This replicates behavior from the legacy Acron plugin.
            PKPContainer::getInstance()->flushOutputBuffer();

            $scheduler->runWebBasedScheduleTaskRunner();
        } catch (Throwable $e) {
            error_log('Web-based schedule task runner failed during request shutdown: ' . $e->getMessage());
        }
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

            return (
                new Schedule(
                    Config::getVar('general', 'timezone', 'UTC')
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
