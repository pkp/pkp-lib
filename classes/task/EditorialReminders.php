<?php

/**
 * @file classes/task/EditorialReminders.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminders
 *
 * @ingroup classes_task
 *
 * @brief Dispatches jobs to send a reminder email to editors of outstanding tasks
 */

namespace PKP\task;

use APP\core\Services;
use APP\facades\Repo;
use APP\services\ContextService;
use PKP\jobs\email\EditorialReminder;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\security\Role;
use PKP\user\Collector;

class EditorialReminders extends ScheduledTask
{
    public function getName()
    {
        return __('mailable.editorialReminder.description');
    }

    protected function executeActions()
    {
        /** @var ContextService $contextService */
        $contextService = Services::get('context');
        $contextIds = $contextService->getIds(['isEnabled' => true]);

        foreach ($contextIds as $contextId) {
            $this->addExecutionLogEntry(
                __('admin.scheduledTask.editorialReminder.logStart', ['contextId' => $contextId]),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );
            $userIds = Repo::user()->getCollector()
                ->filterByContextIds([$contextId])
                ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                ->filterByStatus(Collector::STATUS_ACTIVE)
                ->getIds();

            /** @var int $userId */
            foreach ($userIds as $userId) {
                dispatch(new EditorialReminder($userId, $contextId));
            }
            $this->addExecutionLogEntry(
                __(
                    'admin.scheduledTask.editorialReminder.logEnd',
                    [
                        'count' => $userIds->count(),
                        'userIds' => $userIds->join(', '),
                        'contextId' => $contextId,
                    ]
                ),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );
        }

        return true;
    }
}
