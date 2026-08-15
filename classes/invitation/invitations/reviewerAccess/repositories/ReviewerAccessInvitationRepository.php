<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/repositories/ReviewerAccessInvitationRepository.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvitationRepository
 *
 * @brief Repository for ReviewerAccessInvitation-specific queries
 */

namespace PKP\invitation\invitations\reviewerAccess\repositories;

use PKP\invitation\core\Invitation;
use PKP\invitation\models\InvitationModel;

class ReviewerAccessInvitationRepository
{
    /**
     * Get a ReviewerAccessInvitation by review assignment ID
     */
    public function getByReviewerAssignmentId(int $reviewerAssignmentId): ?Invitation
    {
        $invitationModel = InvitationModel::where('payload->reviewAssignmentId', $reviewerAssignmentId)
            ->orderBy('invitation_id', 'DESC')
            ->first();

        if (is_null($invitationModel)) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }
}
