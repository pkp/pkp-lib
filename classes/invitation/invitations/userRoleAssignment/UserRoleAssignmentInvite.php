<?php

/**
 * @file classes/invitation/invitations/UserRoleAssignmentInvite.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvite
 *
 * @brief Assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use PKP\identity\Identity;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentCreateController;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentReceiveController;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteRedirectController;
use PKP\invitation\invitations\userRoleAssignment\payload\UserGroupPayload;
use PKP\invitation\models\InvitationModel;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use PKP\userGroup\relationships\UserUserGroup;

class UserRoleAssignmentInvite extends Invitation implements IApiHandleable
{
    use HasMailable;
    use ShouldValidate;

    public const INVITATION_TYPE = 'userRoleAssignment';

    protected array $notAccessibleAfterInvite = [
        'userGroupsToAdd',
        'userGroupsToRemove',
    ];

    protected array $notAccessibleBeforeInvite = [
        'orcid',
        'username',
        'password'
    ];

    public ?string $orcid = null;
    public ?string $givenName = null;
    public ?string $familyName = null;
    public ?string $affiliation = null;
    public ?string $country = null;

    public ?string $username = null;
    public ?string $password = null;

    public ?string $emailSubject = null;
    public ?string $emailBody = null;
    public ?bool $existingUser = null;

    public ?array $userGroupsToAdd = null;

    public ?array $userGroupsToRemove = null;

    public static function getType(): string
    {
        return self::INVITATION_TYPE;
    }

    public function getNotAccessibleAfterInvite(): array
    {
        return array_merge(parent::getNotAccessibleAfterInvite(), $this->notAccessibleAfterInvite);
    }

    public function getNotAccessibleBeforeInvite(): array
    {
        return array_merge(parent::getNotAccessibleBeforeInvite(), $this->notAccessibleBeforeInvite);
    }

    public function getMailable(): Mailable
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);
        $locale = $context->getPrimaryLocale();

        // Define the Mailable
        $mailable = new UserRoleAssignmentInvitationNotify($context, $this);
        $mailable->setData($locale);

        // Set the email send data
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

        $inviter = $this->getInviter();

        $reciever = $this->getMailableReceiver($locale);

        $mailable
            ->sender($inviter)
            ->recipients([$reciever])
            ->subject($emailTemplate->getLocalizedData('subject', $locale))
            ->body($emailTemplate->getLocalizedData('body', $locale));

        $this->setMailable($mailable);

        return $this->mailable;
    }

    public function getMailableReceiver(?string $locale = null): Identity 
    {
        $locale = $this->getUsedLocale($locale);

        $receiver = parent::getMailableReceiver($locale);

        if (isset($this->familyName)) {
            $receiver->setFamilyName($this->familyName, $locale);
        }

        if (isset($this->givenName)) {
            $receiver->setGivenName($this->givenName, $locale);
        }

        return $receiver;
    }

    protected function preInviteActions(): void
    {
        // Invalidate any other related invitation
        InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->when(isset($this->invitationModel->userId), function ($query) {
                return $query->byUserId($this->invitationModel->userId);
            })
            ->when(!isset($this->invitationModel->userId) && $this->invitationModel->email, function ($query) {
                return $query->byEmail($this->invitationModel->email);
            })
            ->byContextId($this->invitationModel->contextId)
            ->delete();
    }

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new UserRoleAssignmentInviteRedirectController($this);
    }

    /**
     * @inheritDoc
     */
    public function getCreateInvitationController(Invitation $invitation): CreateInvitationController 
    {
        return new UserRoleAssignmentCreateController($invitation);
    }
    
    /**
     * @inheritDoc
     */
    public function getReceiveInvitationController(Invitation $invitation): ReceiveInvitationController 
    {
        return new UserRoleAssignmentReceiveController($invitation);
    }

    public function getInviteValidationRules(): array
    {
        return ['customValidation' => [
                function ($attribute, $value, $fail) {
                    if (empty($this->invitation->userGroupsToAdd) && empty($this->invitation->userGroupsToRemove)) {
                        $fail(__('invitation.userRoleAssignment.validation.error.noUserGroupChanges'));
                    }
                }
            ]
        ];
    }

    public function getFinaliseValidationRules(): array
    {
        return ['customValidation' => [
                function ($attribute, $value, $fail) {
                    $userId = $this->getUserId();

                    if (isset($userId)) {
                        $user = $this->getExistingUser();

                        if (!isset($user)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.user.mustExist',
                                [
                                    'userId' => $userId
                                ])
                            );
                        }
                    }
                    else if ($this->getEmail()) {
                        $user = Repo::user()->getByEmail($this->getEmail());

                        if (isset($user)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.user.emailMustNotExist', 
                                [
                                    'email' => $this->getEmail()
                                ])
                            );
                        }
                    }
                }
            ]
        ];
    }

    public function getValidationRules(string $validationContext = 'default'): array
    {
        $commonRules = [
            'givenName' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (is_null($this->getUserId()) && empty($value) && $this->getStatus() == InvitationStatus::PENDING) {
                        $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                    }
                }
            ],
            'familyName' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (is_null($this->getUserId()) && empty($value) && $this->getStatus() == InvitationStatus::PENDING) {
                        $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                    }
                }
            ],
            'affiliation' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (is_null($this->getUserId()) && empty($value) && $this->getStatus() == InvitationStatus::PENDING) {
                        $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                    }
                }
            ],
            'country' => [
                'sometimes',
                'string',
                'max:2',
                function ($attribute, $value, $fail) {
                    if (is_null($this->getUserId()) && empty($value) && $this->getStatus() == InvitationStatus::PENDING) {
                        $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                    }
                }
            ],
        ];

        if ($this->getStatus() == InvitationStatus::INITIALIZED) {
            return array_merge($commonRules, [
                'userGroupsToAdd' => [
                    'sometimes',
                    'array',
                    function ($attribute, $value, $fail) {
                        if (is_array($value)) {
                            $userGroupIds = array_column($value, 'userGroupId');
                            if (count($userGroupIds) !== count(array_unique($userGroupIds))) {
                                $fail(__('invitation.userRoleAssignment.validation.error.addUserRoles.duplicateUserGroupId'));
                            }
                        }
                    }
                ],
                'userGroupsToAdd.*' => [
                    'array',
                    function ($attribute, $value, $fail) {
                        $allowedKeys = ['userGroupId', 'masthead', 'dateStart', 'dateEnd'];
                        $unexpectedKeys = array_diff(array_keys($value), $allowedKeys);
                        if (!empty($unexpectedKeys)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.userRoles.unexpectedProperties', [
                                'attribute' => $attribute,
                                'properties' => implode(', ', $unexpectedKeys),
                            ]));
                        }
                    }
                ],
                'userGroupsToAdd.*.userGroupId' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        // Custom validation logic for userGroupId
                        $userGroup = Repo::userGroup()->get($value);

                        if (!isset($userGroup)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupNotExisting', 
                                [
                                    'userGroupId' => $value
                                ])
                            );
                        }
                    }
                ],
                'userGroupsToAdd.*.masthead' => 'required|bool',
                'userGroupsToAdd.*.dateStart' => 'required|date',
                'userGroupsToRemove' => [
                    'sometimes',
                    'array',
                    function ($attribute, $value, $fail) {
                        if (is_null($this->getUserId()) && !empty($value)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.removeUserRoles.cantRemoveFromNonExistingUser'));
                        }
                    }
                ],
                'userGroupsToRemove.*' => [
                    'array',
                    function ($attribute, $value, $fail) {
                        $allowedKeys = ['userGroupId'];
                        $unexpectedKeys = array_diff(array_keys($value), $allowedKeys);
                        if (!empty($unexpectedKeys)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.userRoles.unexpectedProperties', [
                                'attribute' => $attribute,
                                'properties' => implode(', ', $unexpectedKeys),
                            ]));
                        }
                    }
                ],
                'userGroupsToRemove.*.userGroupId' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        $userGroup = Repo::userGroup()->get($value);

                        if (!isset($userGroup)) {
                            $fail(__('invitation.userRoleAssignment.validation.error.removeUserRoles.userGroupNotExisting', 
                                [
                                    'userGroupId' => $value
                                ])
                            );
                        }

                        $user = $this->getExistingUser();
                        if ($user) {
                            $userUserGroups = UserUserGroup::withUserId($user->getId())
                                ->withUserGroupId($userGroup->getId())
                                ->get();

                            if (empty($userUserGroups)) {
                                $fail(__('invitation.userRoleAssignment.validation.error.removeUserRoles.userGroupNotAssignedToUser', 
                                    [
                                        'userGroupName' => $userGroup->getLocalizedName()
                                    ])
                                );
                            }
                        }
                    }
                ]
            ]);
        } elseif ($this->getStatus() == InvitationStatus::PENDING) {
            return array_merge($commonRules, [
                'username' => [
                    'sometimes',
                    'required_with:password',
                    'string',
                    'max:32',
                    function ($attribute, $value, $fail) {
                        if (is_null($this->getUserId())) {
                            if (empty($value)) {
                                $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                                return;
                            }

                            $existingUser = Repo::user()->getByUsername($value, true);

                            if (isset($existingUser)) {
                                $fail(__('invitation.userRoleAssignment.validation.error.username.alreadyExisting', 
                                    [
                                        'username' => $value
                                    ])
                                );
                            }
                        }
                    },
                ],
                'orcid' => ($validationContext === 'populate' || $validationContext === 'refine') ? [
                    'sometimes',
                    'orcid',
                    function ($attribute, $value, $fail) {
                        if (is_null($value)) {
                            $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                        }
                    }
                ] : [
                    'nullable',  // Allows null but must be valid if present
                    'orcid'
                ],
                'password' => [
                    'sometimes',
                    'required_with:username',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        if (is_null($this->getUserId()) && empty($value)) {
                            $fail(__('invitation.validation.required', ['attribute' => $attribute]));
                        }
                    }
                ],
            ]);
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function fillCustomProperties(): void
    {
        $this->fillInvitationWithUserGroups();
    }

    public function fillInvitationWithUserGroups(): void
    {
        if (isset($this->userGroupsToAdd)) {
            $this->fillArrayWithUserGroups($this->userGroupsToAdd);
        }

        if (isset($this->userGroupsToRemove)) {
            $this->fillArrayWithUserGroups($this->userGroupsToRemove);
        }
    }

    private function fillArrayWithUserGroups(array &$userGroups): void
    {
        $userGroups = array_map(function ($userGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userGroup);
            $userGroupPayload->getUserGroupName();
            $userGroup['userGroupPayload'] = $userGroupPayload;
            return $userGroup;
        }, $userGroups);
    }
}
