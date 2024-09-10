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
use PKP\user\User;

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
            'orcid' => $this->getPayload()->orcid,
            'givenName' => $this->getPayload()->givenName,
            'familyName' => $this->getPayload()->familyName,
            'affiliation' => $this->getPayload()->affiliation,
            'country' => $this->getPayload()->country,
            'emailSubject' => $this->getPayload()->emailSubject,
            'emailBody' => $this->getPayload()->emailBody,
            'userGroupsToAdd' => $this->transformUserGroups($this->getPayload()->userGroupsToAdd),
            'userGroupsToRemove' => $this->transformUserGroups($this->getPayload()->userGroupsToRemove),
            'username' => $this->getPayload()->username,
            'sendEmailAddress' => $this->getPayload()->sendEmailAddress,
            'existingUser' => $this->transformUser($this->getExistingUser()),
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
                'userGroupName' => $userGroupModel->getName(null),
                'masthead' => $userGroup['masthead'],
                'dateStart' => $userGroup['dateStart'],
                'dateEnd' => $userGroup['dateEnd'],
            ];
        })->toArray();
    }

    /**
     * Transform the userGroupsToAdd or userGroupsToRemove to include related UserGroup data.
     *
     * @param array|null $userGroups
     * @return array
     */
    protected function transformUser(?User $user): ?array
    {
        if (!isset($user)) {
            return null;
        }

        return [
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'familyName' => $user->getFamilyName(null),
            'givenName' => $user->getGivenName(null),
            'country' => $user->getCountry(),
            'affiliation' => $user->getAffiliation(null)
        ];
    }
}