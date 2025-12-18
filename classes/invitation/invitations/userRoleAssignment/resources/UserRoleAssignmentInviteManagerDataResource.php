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

        if ($payload->shouldUseInviteData) {
            $payload = UserRoleAssignmentInvitePayload::fromArray($payload->inviteStagePayload);

            $newUser = $this->createNewUserFromPayload($payload);
        } else {
            $existingUser = $this->getExistingUser();

            if (!isset($existingUser)) {
                $newUser = $this->createNewUserFromPayload($payload);
            }
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
            'existingUser' => $this->transformUser($existingUser),
            'newUser' => $this->transformUser($newUser),
        ]);
    }
}
