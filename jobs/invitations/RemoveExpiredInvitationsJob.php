<?php

declare(strict_types=1);

/**
 * @file jobs/invitations/RemoveExpiredInvitationsJob.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveExpiredInvitationsJob
 *
 * @ingroup jobs
 *
 * @brief Job to remove all expired invitations
 */

namespace PKP\jobs\invitations;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class RemoveExpiredInvitationsJob extends BaseJob
{
    /**
     * Execute the job.
     */
    public function handle()
    {
        $expiredInvitations = Repo::invitation()
            ->expired()
            ->getMany();

        foreach ($expiredInvitations as $expiredInvitation) {
            Repo::invitation()
                ->delete($expiredInvitation->getId());
        }
    }
}
