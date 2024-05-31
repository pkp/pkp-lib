<?php

/**
 * @file jobs/invitations/RemoveExpiredInvitationsJob.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveExpiredInvitationsJob
 *
 *
 * @brief Job to remove all expired invitations
 */

namespace PKP\jobs\invitations;

use APP\facades\Repo;
use PKP\invitation\models\InvitationModel;
use PKP\jobs\BaseJob;

class RemoveExpiredInvitationsJob extends BaseJob
{
    /**
     * Execute the job.
     */
    public function handle()
    {
        InvitationModel::expired()
            ->delete();
    }
}
