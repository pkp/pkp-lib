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
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitePayload;
use PKP\invitation\invitations\userRoleAssignment\rules\AllowedKeysRule;
use PKP\invitation\invitations\userRoleAssignment\rules\RemoveUserGroupRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UserGroupExistsRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UsernameExistsRule;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

class UserRoleAssignmentInvitePayload extends InvitePayload
{
    public function __construct(
        public ?string $orcid = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $affiliation = null,
        public ?string $country = null,
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
        $validationRules = [
            'givenName' => [
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'string',
                'max:255',
            ],
            'familyName' => [
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'string',
                'max:255',
            ],
            'affiliation' => [
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'string',
                'max:255',
            ],
            'country' => [
                Rule::excludeIf(!is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE]), ['nullable']),
                Rule::requiredIf($validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'sometimes',
                'string',
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
            ],
            'userGroupsToAdd.*.masthead' => 'required|bool',
            'userGroupsToAdd.*.dateStart' => 'required|date|after_or_equal:today',
            'userGroupsToRemove' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(is_null($invitation->getUserId())), 
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
            'username' => [
                'bail',
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::requiredIf(is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'max:32',
                new UsernameExistsRule(),
                Rule::when($validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE, ['nullable']),
                'required_with:password',
            ],
            'password' => [
                'bail',
                Rule::prohibitedIf(!is_null($invitation->getUserId())),
                Rule::requiredIf(is_null($invitation->getUserId()) && $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE),
                'max:255',
                Rule::when($validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE, ['nullable']),
                'required_with:username'
            ],
            'orcid' => [
                Rule::when(in_array($validationContext, [ValidationContext::VALIDATION_CONTEXT_INVITE, ValidationContext::VALIDATION_CONTEXT_FINALIZE]), ['nullable']),
            ],
        ];

        return $validationRules;
    }

    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $messages = [
            'userGroupsToRemove.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForNonExistingUser'),
            'givenName.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'familyName.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'affiliation.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'country.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'username.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'password.prohibited' => __('invitation.userRoleAssignment.error.update.prohibitedForExistingUser'),
            'userGroupsToAdd.*.dateStart.after_or_equal' => __('invitation.userRoleAssignment.userGroup.startDate.mustBeAfterToday'),
        ];

        return $messages;
    }
}
