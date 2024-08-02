<?php

/**
 * @file classes/task/DepositDois.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositDois
 *
 * @ingroup classes_task
 *
 * @brief Dispatches job to automatically deposit DOIs for all configured contexts
 */

namespace PKP\task;

use APP\services\ContextService;
use PKP\jobs\doi\DepositContext;
use PKP\scheduledTask\ScheduledTask;

class DepositDois extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.depositDois');
    }

    /**
     * @inheritDoc
     */
    protected function executeActions(): bool
    {
        /** @var ContextService $contextService */
        $contextService = app()->get('context');
        $contextIds = $contextService->getIds(['isEnabled' => true]);

        foreach ($contextIds as $contextId) {
            dispatch(new DepositContext($contextId));
        }

        return true;
    }
}
