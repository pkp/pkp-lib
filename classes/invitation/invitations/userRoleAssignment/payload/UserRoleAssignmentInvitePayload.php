<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/payload/UserRoleAssignmentInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitePayload
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\payload;

use Illuminate\Validation\Rule;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\InvitePayload;
use PKP\invitation\invitations\userRoleAssignment\rules\AddUserGroupRule;
use PKP\invitation\invitations\userRoleAssignment\rules\AllowedKeysRule;
use PKP\invitation\invitations\userRoleAssignment\rules\NotNullIfPresent;
use PKP\invitation\invitations\userRoleAssignment\rules\ProhibitedIncludingNull;
use PKP\invitation\invitations\userRoleAssignment\rules\RemoveUserGroupRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UserGroupExistsRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UsernameExistsRule;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

class UserRoleAssignmentInvitePayload extends InvitePayload
{
    public function __construct(
        public ?string $userOrcid = null,
        public ?array $givenName = null,
        public ?array $familyName = null,
        public ?array $affiliation = null,
        public ?string $userCountry = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $emailSubject = null,
        public ?string $emailBody = null,
        public ?array $userGroupsToAdd = null,
        public ?array $userGroupsToRemove = null,
        public ?bool $passwordHashed = null,
        public ?string $sendEmailAddress = null,
    ) 
    {
        parent::__construct(get_object_vars($this));


    }

    public function getValidationRules(UserRoleAssignmentInvite $invitation, ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $context = $invitation->getContext();
        $allowedLocales = $context->getSupportedFormLocales();

        $validationRules = [
            'givenName' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales), // Apply the custom rule
            ],
            'givenName.*' => [
                'string',
                'max:255',
            ],
            'familyName' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales), // Apply the custom rule
            ],
            'familyName.*' => [
                'string',
                'max:255',
            ],
            'affiliation' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'array',
                new AllowedKeysRule($allowedLocales), // Apply the custom rule
            ],
            'affiliation.*' => [
                'string',
                'max:255',
            ],
            'userCountry' => [
                'bail',
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                new ProhibitedIncludingNull(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
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
            ],
            'userGroupsToAdd' => [
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE),
                'sometimes',
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
                new AddUserGroupRule($invitation),
            ],
            'userGroupsToAdd.*.masthead' => 'required|bool',
            'userGroupsToAdd.*.dateStart' => 'required|date|after_or_equal:today',
            'userGroupsToRemove' => [
                'sometimes',
                'bail',
                new ProhibitedIncludingNull(is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE, ValidationContext::VALIDATION_CONTEXT_FINALIZE]), ['nullable']),
            ],
            'userGroupsToRemove.*' => [
                'array',
                new AllowedKeysRule(['userGroupId']),
            ],
            'userGroupsToRemove.*.userGroupId' => [
                'distinct',
                'required',
                'integer',
                new UserGroupExistsRule(),
                new RemoveUserGroupRule($invitation),
            ],
            'userOrcid' => [
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE, ValidationContext::VALIDATION_CONTEXT_FINALIZE]), ['nullable']),
                'orcid'
            ],
        ];

        return $validationRules;
    }

    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        return [];
    }
}
