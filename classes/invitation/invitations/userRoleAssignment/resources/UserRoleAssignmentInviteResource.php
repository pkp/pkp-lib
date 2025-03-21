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
use PKP\context\Context;
use PKP\invitation\invitations\userRoleAssignment\payload\UserRoleAssignmentInvitePayload;
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

        $payload = $this->getPayload();

        if (!isset($existingUser)) {
            $newUser = new User();

            $newUser->setAffiliation($payload->affiliation, null);
            $newUser->setFamilyName($payload->familyName, null);
            $newUser->setGivenName($payload->givenName, null);
            $newUser->setCountry($payload->userCountry);
            $newUser->setUsername($payload->username);
            $newUser->setEmail($payload->sendEmailAddress);
        }

        // Return specific fields from the UserRoleAssignmentInvite
        return array_merge($baseData, [
            'orcid' => $payload->orcid,
            'orcidAccessDenied' => $payload->orcidAccessDenied,
            'orcidAccessExpiresOn' => $payload->orcidAccessExpiresOn,
            'orcidAccessScope' => $payload->orcidAccessScope,
            'orcidAccessToken' => $payload->orcidAccessToken,
            'orcidIsVerified' => $payload->orcidIsVerified,
            'orcidRefreshToken' => $payload->orcidRefreshToken,
            'givenName' => $payload->givenName,
            'familyName' => $payload->familyName,
            'affiliation' => $payload->affiliation,
            'country' => $payload->userCountry,
            'emailSubject' => $payload->emailSubject,
            'emailBody' => $payload->emailBody,
            'userGroupsToAdd' => $this->transformUserGroups($payload->userGroupsToAdd),
            'username' => $payload->username,
            'sendEmailAddress' => $payload->sendEmailAddress,
            'existingUser' => $this->transformUser($this->getExistingUser()),
            'newUser' => $this->transformUser($newUser),
        ]);
    }

    /**
     * Transform invitation payload and user data
     * @param int|null $userId
     * @param array $payload
     * @param Context $context
     * @return array
     */
    public function transformInvitationPayload(?int $userId, array $payload, Context $context): array
    {
        $invitationPayload = UserRoleAssignmentInvitePayload::fromArray($payload)->toArray();
        $user = null;
        if($userId){
            $user = Repo::user()->get($userId,true);
            $invitationPayload = UserRoleAssignmentInvitePayload::fromArray($user->getAllData())->toArray();
        }
        $invitationPayload['userId'] = $user ? $user->getId() : $userId;
        $invitationPayload['inviteeEmail'] = $user ? $user->getEmail() : $invitationPayload['sendEmailAddress'];
        $invitationPayload['country'] = $user ? $user->getCountryLocalized() : $invitationPayload['userCountry'];
        $invitationPayload['biography'] = $user?->getBiography(null);
        $invitationPayload['phone'] = $user?->getPhone();
        $invitationPayload['mailingAddress'] = $user?->getMailingAddress();
        $invitationPayload['signature'] = $user?->getSignature(null);
        $invitationPayload['locales'] = $user? $this->transformWorkingLanguages($context,$user->getLocales()) : null;
        $invitationPayload['reviewInterests'] = $user?->getInterestString();
        $invitationPayload['homePageUrl'] = $user?->getUrl();
        $invitationPayload['disabled'] = $user?->getData('disabled');
        $invitationPayload['userGroupsToAdd'] = !$payload['userGroupsToAdd'] ? [] : $invitationPayload['userGroupsToAdd'];
        $invitationPayload['currentUserGroups'] = !$userId ? [] : $this->transformCurrentUserGroups($userId,$context);
        $invitationPayload['userGroupsToRemove'] = [];
        $invitationPayload['emailComposer'] = [
            'emailBody' => $invitationPayload['emailBody'],
            'emailSubject' => $invitationPayload['emailSubject'],
        ];

        // removing security related data
        unset($invitationPayload["username"]);
        unset($invitationPayload["password"]);
        unset($invitationPayload["orcidAccessToken"]);
        unset($invitationPayload["orcidRefreshToken"]);
        unset($invitationPayload["passwordHashed"]);

        return [
            'invitationPayload' => $invitationPayload,
            'user' => $user
        ];
    }
}
