<?php

namespace PKP\invitation\invitations\reviewerAccess\resources;

use Illuminate\Http\Request;
use PKP\context\Context;
use PKP\invitation\invitations\reviewerAccess\payload\ReviewerAccessInvitePayload;
use PKP\user\User;

class ReviewerAccessInviteResource extends BaseReviewerAccessInviteResource
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
            'submissionId' => $payload->submissionId,
            'reviewRoundId' => $payload->reviewRoundId,
            'reviewAssignmentId' => $payload->reviewAssignmentId,
            'reviewMethod' => $payload->reviewMethod,
            'responseDueDate' => $payload->responseDueDate,
            'reviewDueDate' => $payload->reviewDueDate,
            'sendEmailAddress' => $payload->sendEmailAddress,
            'existingUser' => $this->transformUser($this->getExistingUser()),
            'newUser' => $this->transformUser($newUser),
            'submission' => $this->transformSubmission($payload->submissionId),
        ]);
    }

    /**
     * Transform invitation payload
     * @param int|null $userId
     * @param array $payload
     * @param Context $context
     * @return ReviewerAccessInvitePayload
     */
    public function transformInvitationPayload(?int $userId, array $payload, Context $context): object
    {
        $invitationPayload = ReviewerAccessInvitePayload::fromArray($payload)->toArray();
        $invitationPayload['userId'] = !$userId ? null : $userId;
        $invitationPayload['inviteeEmail'] = !$userId ?$invitationPayload['sendEmailAddress']:$payload['email'];
        $invitationPayload['userGroupsToAdd'] = !$invitationPayload['userGroupsToAdd'] ? [] :$invitationPayload['userGroupsToAdd'];
        $invitationPayload['currentUserGroups'] = !$userId ? [] : $this->transformCurrentUserGroups($userId,$context);

        return (object)$invitationPayload;
    }

    /**
     * Transform user data for invitation view
     * @param User $user
     * @param $context
     * @return array
     */
    public function transformInvitationUserData(User $user, $context): array
    {
        $userData = [];
        $userData['country'] = $user->getCountryLocalized();
        $userData['biography'] = $user->getBiography(null);
        $userData['phone'] = $user->getPhone();
        $userData['mailingAddress'] = $user->getMailingAddress();
        $userData['signature'] = $user->getSignature(null);
        $userData['locales'] = $this->transformUserWorkingLanguages($context, $user->getLocales());
        $userData['reviewInterests'] = $user->getInterestString();
        $userData['homePageUrl'] = $user->getUrl();
        $userData['disabled'] = $user->getData('disabled');
        return $userData;
    }

    /**
     * get user working languages
     * @param Context $context
     * @param array $userLocales
     * @return string
     */
    private function transformUserWorkingLanguages(Context $context, array $userLocales): string
    {
        $locales = $context->getSupportedLocaleNames();
        return join(__('common.commaListSeparator'), array_map(fn($key) => $locales[$key], $userLocales));
    }
}
