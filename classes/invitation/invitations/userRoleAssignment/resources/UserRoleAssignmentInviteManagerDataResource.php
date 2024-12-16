<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/resources/UserRoleAssignmentInviteManagerDataResource.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInviteManagerDataResource
 *
 * @brief A JsonResource to transform the UserRoleAssignmentInvite to JSON for API responses
 */

namespace PKP\invitation\invitations\userRoleAssignment\resources;

use Illuminate\Http\Request;
use PKP\invitation\invitations\userRoleAssignment\payload\UserRoleAssignmentInvitePayload;
use PKP\user\User;

class UserRoleAssignmentInviteManagerDataResource extends BaseUserRoleAssignmentInviteResource
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

        if ($this->getPayload()->shouldUseInviteData) {
            $payload = UserRoleAssignmentInvitePayload::fromArray($this->getPayload()->inviteStagePayload);

            $newUser = $this->createNewUserFromPayload($payload);
        } else {
            $existingUser = $this->getExistingUser();

            if (!isset($existingUser)) {
                $newUser = $this->createNewUserFromPayload($payload);
            }
        }

        // Return specific fields from the UserRoleAssignmentInvite
        return array_merge($baseData, [
            'orcid' => $payload->userOrcid,
            'givenName' => $payload->givenName,
            'familyName' => $payload->familyName,
            'affiliation' => $payload->affiliation,
            'country' => $payload->userCountry,
            'emailSubject' => $payload->emailSubject,
            'emailBody' => $payload->emailBody,
            'userGroupsToAdd' => $this->transformUserGroups($payload->userGroupsToAdd),
            'username' => $payload->username,
            'sendEmailAddress' => $payload->sendEmailAddress,
            'existingUser' => $this->transformUser($existingUser),
            'newUser' => $this->transformUser($newUser),
        ]);
    }
}