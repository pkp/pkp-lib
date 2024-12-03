<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/resources/BaseUserRoleAssignmentInviteResource.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseUserRoleAssignmentInviteResource
 *
 * @brief A JsonResource to transform the UserRoleAssignmentInvite to JSON for API responses
 */

namespace PKP\invitation\invitations\userRoleAssignment\resources;

use APP\facades\Repo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\invitation\invitations\userRoleAssignment\payload\UserRoleAssignmentInvitePayload;
use PKP\user\User;

class BaseUserRoleAssignmentInviteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        // Get all attributes of the invitationModel as an array
        $invitationData = $this->invitationModel->toArray();

        return $invitationData;
    }

    /**
     * Transform the userGroupsToAdd to include related UserGroup data.
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
                'userGroupName' => $userGroupModel->getLocalizedData('name'),
                'masthead' => $userGroup['masthead'],
                'dateStart' => $userGroup['dateStart'],
                'dateEnd' => $userGroup['dateEnd'],
            ];
        })->toArray();
    }

    /**
     * Transform the userGroupsToAdd to include related UserGroup data.
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
            'affiliation' => $user->getAffiliation(null),
            'orcid' => $user->getOrcid(),
            'orcidIsVerified' => $user->hasVerifiedOrcid(),
        ];
    }

    protected function createNewUserFromPayload(UserRoleAssignmentInvitePayload $payload): User
    {
        $newUser = new User();

        $newUser->setAffiliation($payload->affiliation, null);
        $newUser->setFamilyName($payload->familyName, null);
        $newUser->setGivenName($payload->givenName, null);
        $newUser->setCountry($payload->userCountry);
        $newUser->setUsername($payload->username);
        $newUser->setEmail($payload->sendEmailAddress);

        return $newUser;
    }
}
