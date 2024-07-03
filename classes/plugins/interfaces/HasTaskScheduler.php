<?php

/**
 * @file classes/plugins/interfaces/HasTaskScheduler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasTaskScheduler
 *
 * @brief Interfaces for plugin to implement to add schedule tasks
 */

namespace PKP\plugins\interfaces;

use APP\scheduler\Scheduler;

interface HasTaskScheduler
{
    /**
     * Register schedule tasks into the core schedule
     *
     * @param \APP\scheduler\Scheduler|null $scheduler The core app level specific scheduler
     * @return void
     */
    public function registerSchedules(?Scheduler $scheduler = null): void;
}