<?php

/**
 * @file classes/task/RemoveExpiredInvitations.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveExpiredInvitations
 *
 * @ingroup classes_task
 *
 * @brief Dispatches job to automatically remove expired invitations
 */

namespace PKP\task;

use PKP\jobs\invitations\RemoveExpiredInvitationsJob;
use PKP\scheduledTask\ScheduledTask;

class RemoveExpiredInvitations extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.removeExpiredInvitations');
    }

    /**
     * @inheritDoc
     */
    protected function executeActions(): bool
    {
        dispatch(new RemoveExpiredInvitationsJob());

        return true;
    }
}
