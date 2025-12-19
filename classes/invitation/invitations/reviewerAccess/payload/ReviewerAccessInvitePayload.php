<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/payload/ReviewerAccessInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvitePayload
 *
 * @brief Payload for the ReviewerAccessInvite invitation
 */

namespace PKP\invitation\invitations\reviewerAccess\payload;

use APP\facades\Repo;
use PKP\db\DAORegistry;
use Illuminate\Validation\Rule;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\InvitePayload;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\invitations\userRoleAssignment\rules\AllowedKeysRule;
use PKP\invitation\invitations\userRoleAssignment\rules\NotNullIfPresent;
use PKP\invitation\invitations\userRoleAssignment\rules\ProhibitedIncludingNull;
use PKP\invitation\invitations\userRoleAssignment\rules\UserGroupExistsRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UsernameExistsRule;
use PKP\security\Role;

class ReviewerAccessInvitePayload extends InvitePayload
{
    public function __construct(
        public ?string $orcid = null,
        public ?string $orcidAccessDenied = null,
        public ?string $orcidAccessExpiresOn = null,
        public ?string $orcidAccessScope = null,
        public ?string $orcidAccessToken = null,
        public ?bool $orcidIsVerified = null,
        public ?string $orcidRefreshToken = null,
        public ?array  $givenName = null,
        public ?array  $familyName = null,
        public ?array  $affiliation = null,
        public ?string $userCountry = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $emailSubject = null,
        public ?string $emailBody = null,
        public ?array  $userGroupsToAdd = null,
        public ?bool   $passwordHashed = null,
        public ?string $sendEmailAddress = null,
        public ?array $inviteStagePayload = null,
        public ?bool $shouldUseInviteData = null,
        public ?int $submissionId = null,
        public ?string $reviewMethod = null,
        public ?string $responseDueDate = null,
        public ?string $reviewDueDate = null,
        public ?int $reviewRoundId = null,
        public ?int $reviewAssignmentId = null,
        public ?array $selectedFileIds = null,
        public ?array $userInterests = null,
    )
    {
        parent::__construct(get_object_vars($this));
    }

    public function getValidationRules(ReviewerAccessInvite $invitation, ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $context = $invitation->getContext();
        $allowedLocales = $context->getSupportedFormLocales();
        $primaryLocale = $context->getPrimaryLocale();

        $user = $invitation->getUserId()
            ? Repo::user()->get($invitation->getUserId(), true)
            : null;
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->getSite();

        $validationRules = [
            'givenName' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales),
            ],
            'givenName.*' => [
                'nullable', // Make optional for other locales
                'string',
                'max:255',
            ],
            "givenName.{$primaryLocale}" => [
                'sometimes',
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_REFINE),
            ],
            'familyName' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE, ValidationContext::VALIDATION_CONTEXT_FINALIZE]), ['nullable']),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales),
            ],
            'familyName.*' => [
                'nullable', // Make optional for all locales
                'string',
                'max:255',
            ],
            'affiliation' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE, ValidationContext::VALIDATION_CONTEXT_FINALIZE]), ['nullable']),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales),
            ],
            'affiliation.*' => [
                'nullable', // Make optional for all locales
                'string',
                'max:255',
            ],
            'userCountry' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes', // Applies the rule only if userCountry exists in the request
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_REFINE),
                'string',
                'max:255',
            ],
            'username' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::requiredIf(is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new UsernameExistsRule(),
                Rule::when($validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE, ['nullable']),
                new NotNullIfPresent(),
                'required_with:password',
                'max:32',
            ],
            'password' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::requiredIf(is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::when($validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE, ['nullable']),
                new NotNullIfPresent(),
                'required_with:username',
                'max:255',
                'min:' . $site->getMinPasswordLength(),
            ],
            'userGroupsToAdd' => [
                Rule::requiredIf(
                    !($user?->hasRole([Role::ROLE_ID_REVIEWER], $context->getId()) ?? false)
                    && $validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE
                ),
                'nullable',
                'array',
                'bail',
            ],
            'userGroupsToAdd.*' => [
                'array',
                new AllowedKeysRule(['userGroupId', 'masthead', 'dateStart', 'dateEnd']),
            ],
            'userGroupsToAdd.*.userGroupId' => [
                'distinct',
                'required',
                'integer',
                new UserGroupExistsRule(),
            ],
            'userGroupsToAdd.*.masthead' => 'required|bool',
            'userGroupsToAdd.*.dateStart' => 'required|date',
            // FIXME: A duplication of existing rules in user schema. Can they be reused?
            'orcid' => [
                'nullable',
                'orcid'
            ],
            'orcidAccessDenied' => [
                'nullable',
                'string',
                'max:255',
            ],
            'orcidAccessExpiresOn' => [
                'nullable',
                'string',
                'max:255',
            ],
            'orcidAccessScope' => [
                'nullable',
                'string',
                'max:255',
            ],
            'orcidAccessToken' => [
                'nullable',
                'string',
                'max:255',
            ],
            'orcidIsVerified' => [
                'nullable',
                'boolean',
            ],
            'orcidRefreshToken' => [
                'nullable',
                'string',
                'max:255',
            ],
            'shouldUseInviteData' => [
                new ProhibitedIncludingNull($validationContext === ValidationContext::VALIDATION_CONTEXT_REFINE||$validationContext === ValidationContext::VALIDATION_CONTEXT_POPULATE),
            ],
            'submissionId' => [
                'sometimes',
                'required',
                'integer'
            ],
            'reviewMethod' => [
                'sometimes',
                'required',
                'integer',
            ],
            'responseDueDate' => [
                'sometimes',
                'required',
                'date'
            ],
            'reviewDueDate' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:responseDueDate'
            ],
            'reviewRoundId' => [
                'sometimes',
                'required',
                'integer'
            ],
            'selectedFileIds' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'userInterests' => [
                'sometimes',
                'nullable',
                'array'
            ],
        ];

        return $validationRules;
    }

    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        return [
            'reviewDueDate.after_or_equal' => __('reviewerInvitation.validator.reviewDueDate.afterOrEqual'),
        ];
    }
}
