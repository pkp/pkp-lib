<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/resources/UserRoleAssignmentInviteResource.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInviteResource
 *
 * @brief A JsonResource to transform the UserRoleAssignmentInvite to JSON for API responses
 */

namespace PKP\invitation\invitations\userRoleAssignment\resources;

use Illuminate\Http\Request;
use PKP\user\User;

class UserRoleAssignmentInviteResource extends BaseUserRoleAssignmentInviteResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        // Get all attributes of the invitationModel as an array
        $baseData = parent::toArray($request);

        $existingUser = $this->getExistingUser();
        $newUser = null;

        if (!isset($existingUser)) {
            $newUser = new User();

            $newUser->setAffiliation($this->getPayload()->affiliation, null);
            $newUser->setFamilyName($this->getPayload()->familyName, null);
            $newUser->setGivenName($this->getPayload()->givenName, null);
            $newUser->setCountry($this->getPayload()->userCountry);
            $newUser->setUsername($this->getPayload()->username);
            $newUser->setEmail($this->getPayload()->sendEmailAddress);
        }

        // Return specific fields from the UserRoleAssignmentInvite
        return array_merge($baseData, [
            'orcid' => $this->getPayload()->userOrcid,
            'givenName' => $this->getPayload()->givenName,
            'familyName' => $this->getPayload()->familyName,
            'affiliation' => $this->getPayload()->affiliation,
            'country' => $this->getPayload()->userCountry,
            'emailSubject' => $this->getPayload()->emailSubject,
            'emailBody' => $this->getPayload()->emailBody,
            'userGroupsToAdd' => $this->transformUserGroups($this->getPayload()->userGroupsToAdd),
            'username' => $this->getPayload()->username,
            'sendEmailAddress' => $this->getPayload()->sendEmailAddress,
            'existingUser' => $this->transformUser($this->getExistingUser()),
            'newUser' => $this->transformUser($newUser),
        ]);
    }
}