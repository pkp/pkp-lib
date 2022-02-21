<?php

/**
 * @file classes/core/SchedulerServiceProvider.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SchedulerServiceProvider
 * @ingroup core
 *
 * @brief Enables Scheduler Service Provider on the application
 */

namespace PKP\core;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use PKP\core\interfaces\SchedulerServiceProviderInterface;

class SchedulerServiceProvider extends ServiceProvider implements SchedulerServiceProviderInterface
{
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $this->scheduleTasks($schedule);
        });
    }

    /**
     * Register application services
     *
     * Services registered on the app container here can be automatically
     * injected as dependencies to classes that are instantiated by the
     * app container.
     *
     * @see https://laravel.com/docs/8.x/container#automatic-injection
     * @see https://laravel.com/docs/8.x/providers#the-register-method
     */
    public function register()
    {
        //
    }

    /**
     * Schedule Tasks into an Illuminate\Console\Scheduling\Schedule implementation
     *
     *
     */
    public function scheduleTasks(Schedule $scheduleBag): void
    {
        //
    }
}
