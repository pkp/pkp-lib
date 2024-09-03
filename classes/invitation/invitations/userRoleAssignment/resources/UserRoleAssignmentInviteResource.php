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

use APP\facades\Repo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleAssignmentInviteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        // Get all attributes of the invitationModel as an array
        $invitationData = $this->invitationModel->toArray();

        // Return specific fields from the UserRoleAssignmentInvite
        return array_merge($invitationData, [
            'orcid' => $this->getSpecificPayload()->orcid,
            'givenName' => $this->getSpecificPayload()->givenName,
            'familyName' => $this->getSpecificPayload()->familyName,
            'affiliation' => $this->getSpecificPayload()->affiliation,
            'country' => $this->getSpecificPayload()->country,
            'emailSubject' => $this->getSpecificPayload()->emailSubject,
            'emailBody' => $this->getSpecificPayload()->emailBody,
            'userGroupsToAdd' => $this->transformUserGroups($this->getSpecificPayload()->userGroupsToAdd),
            'userGroupsToRemove' => $this->transformUserGroups($this->getSpecificPayload()->userGroupsToRemove),
            'username' => $this->getSpecificPayload()->username,
        ]);
    }

    /**
     * Transform the userGroupsToAdd or userGroupsToRemove to include related UserGroup data.
     *
     * @param array|null $userGroups
     * @return array
     */
    protected function transformUserGroups(?array $userGroups)
    {
        return collect($userGroups)->map(function ($userGroup) {
            $userGroupModel = Repo::userGroup()->get($userGroup['userGroupId']);

            return [
                'userGroupId' => $userGroup['userGroupId'],
                'userGroupName' => $userGroupModel->getLocalizedName(),
                'masthead' => $userGroup['masthead'],
                'dateStart' => $userGroup['dateStart'],
                'dateEnd' => $userGroup['dateEnd'],
            ];
        })->toArray();
    }
}