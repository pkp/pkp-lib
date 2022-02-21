<?php
/**
 * @file classes/core/interfaces/SchedulerServiceProviderInterface.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SchedulerServiceProviderInterface
 *
 * @brief An interface describing the methods an SchedulerServiceProvider class must implement.
 */

namespace PKP\core\interfaces;

use Illuminate\Console\Scheduling\Schedule;

interface SchedulerServiceProviderInterface
{
    /**
     * Schedule Tasks into an Illuminate\Console\Scheduling\Schedule implementation
     *
     *
     */
    public function scheduleTasks(Schedule $scheduleBag): void;
}
