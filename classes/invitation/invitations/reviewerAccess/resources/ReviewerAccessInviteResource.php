<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/resources/ReviewerAccessInviteResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteResource
 *
 * @brief A JsonResource to transform the ReviewerAccessInvite to JSON for API responses
 */

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
            $newUser = $payload->createUser();
        }

        // Return transformed fields
        return array_merge($baseData, [
            'userGroupsToAdd' => $this->transformUserGroups($payload->userGroupsToAdd),
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
