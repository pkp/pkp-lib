<?php

/**
 * @file classes/plugins/interfaces/HasTaskScheduler.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasTaskScheduler
 *
 * @brief Provides an interface for plugins that require the implementation of scheduled tasks
 */

namespace PKP\plugins\interfaces;

use PKP\scheduledTask\PKPScheduler;

interface HasTaskScheduler
{
    /**
     * Register schedule tasks into the core schedule
     */
    public function registerSchedules(PKPScheduler $scheduler): void;
}
