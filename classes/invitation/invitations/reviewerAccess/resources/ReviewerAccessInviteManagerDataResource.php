<?php

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
            'submissionId' => $payload->submissionId,
            'reviewRoundId' => $payload->reviewRoundId,
            'reviewAssignmentId' => $payload->reviewAssignmentId,
            'reviewMethod' => $payload->reviewMethod,
            'responseDueDate' => $payload->responseDueDate,
            'reviewDueDate' => $payload->reviewDueDate,
            'sendEmailAddress' => $payload->sendEmailAddress,
            'existingUser' => $this->transformUser($existingUser),
            'newUser' => $this->transformUser($newUser),
            'userInterests' => $payload->userInterests,
            'submission' => $this->transformSubmission($payload->submissionId),
        ]);
    }
}
