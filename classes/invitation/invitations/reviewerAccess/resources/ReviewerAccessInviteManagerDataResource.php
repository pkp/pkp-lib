<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/resources/ReviewerAccessInviteManagerDataResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteManagerDataResource
 *
 * @brief A JsonResource to transform the ReviewerAccessInvite to JSON for API responses
 */

namespace PKP\invitation\invitations\reviewerAccess\resources;

use Illuminate\Http\Request;
use PKP\invitation\invitations\reviewerAccess\payload\ReviewerAccessInvitePayload;

class ReviewerAccessInviteManagerDataResource extends BaseReviewerAccessInviteResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        $baseData = parent::toArray($request);

        $payload = $this->getPayload();

        $existingUser = null;
        $newUser = null;

        if ($payload->shouldUseInviteData) {
            $payload = ReviewerAccessInvitePayload::fromArray($payload->inviteStagePayload);

            $newUser = $payload->createUser();
        } else {
            $existingUser = $this->getExistingUser();

            if (!isset($existingUser)) {
                $newUser = $payload->createUser();
            }
        }

        // Return transformed fields
        return array_merge($baseData, [
            'userGroupsToAdd' => $this->transformUserGroups($payload->userGroupsToAdd),
            'existingUser' => $this->transformUser($existingUser),
            'newUser' => $this->transformUser($newUser),
            'submission' => $this->transformSubmission($payload->submissionId),
        ]);
    }
}
