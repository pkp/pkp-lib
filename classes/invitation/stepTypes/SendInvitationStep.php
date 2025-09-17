<?php

/**
 * @file classes/invitation/stepType/SendInvitationStep.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendInvitationStep
 *
 * @brief create accept invitation steps.
 */

namespace PKP\invitation\stepTypes;

use APP\core\Application;
use Exception;
use PKP\components\forms\invitation\ReviewerReviewDetailsForm;
use PKP\components\forms\invitation\UserDetailsForm;
use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\sections\Email;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\mail\mailables\ReviewerAccessInvitationNotify;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use PKP\security\Role;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use stdClass;

class SendInvitationStep extends InvitationStepTypes
{
    private ?Invitation $invitation;
    private Context $context;
    private ?User $user;
    private string $invitationType;
    public function __construct(?Invitation $invitation, Context $context, ?User $user,string $invitationType)
    {
        $this->invitation = $invitation;
        $this->context = $context;
        $this->user = $user;
        $this->invitationType = $invitationType;
    }
    /**
     * get send invitation steps
     *
     * @throws Exception
     */
    public function getSteps(): array
    {
        $steps = [];
        if ((!$this->invitation  && !$this->user)) {
            $steps[] = $this->invitationSearchUser();
        }
        if(self::INVITATION_USER_ROLE_ASSIGNMENT === $this->invitationType || (self::INVITATION_REVIEWER_ACCESS_INVITE === $this->invitationType && !$this->user)) {
            $steps[] = $this->invitationDetailsForm();
        }
        if(self::INVITATION_REVIEWER_ACCESS_INVITE === $this->invitationType) {
            $steps[] = $this->invitationReviewDetails();
        }
        $steps[] = $this->invitationInvitedEmail();
        return $steps;
    }

    /**
     * create search user section
     */
    private function invitationSearchUser(): stdClass
    {
        $sections = new Sections(
            'searchUserForm',
            __('userInvitation.searchUser.stepName'),
            'form',
            'UserInvitationSearchFormStep',
            __('userInvitation.searchUser.stepDescription'),
        );
        $sections->addSection(
            null,
            [
                'validateFields' => []
            ]
        );
        $step = new Step(
            'searchUser',
            __('userInvitation.searchUser.stepName'),
            __('userInvitation.searchUser.stepLabel'),
            __('userInvitation.searchUser.nextButtonLabel'),
            'emptySection',
            __('userInvitation.searchUser.stepDescription'),
            true
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create user details form section
     *
     * @throws Exception
     */
    private function invitationDetailsForm(): stdClass
    {
        $localeNames = $this->context->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $sections = new Sections(
            'userDetails',
            __('userInvitation.enterDetails.stepName'),
            'form',
            'UserInvitationDetailsFormStep',
            __('userInvitation.enterDetails.stepDescription'),
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('userInvitation.enterDetails.stepName'),
                __('userInvitation.enterDetails.stepDescription'),
                new UserDetailsForm('users', $locales),
            ),
            [
                'validateFields' => [],
                'userGroups' => $this->getAllUserGroups(),
            ]
        );
        $step = new Step(
            'userDetails',
            __('userInvitation.enterDetails.stepName'),
            __('userInvitation.enterDetails.stepLabel'),
            __('userInvitation.enterDetails.nextButtonLabel'),
            'form',
            __('userInvitation.enterDetails.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create email composer for send invite
     *
     * @throws Exception
     */
    private function invitationInvitedEmail(): stdClass
    {
        $sections = new Sections(
            'userInvitedEmail',
            __('userInvitation.sendMail.stepLabel'),
            'email',
            'UserInvitationEmailComposerStep',
            __('userInvitation.sendMail.stepName'),
        );
        $fakeInvitation = $this->getFakeInvitation($this->invitationType);
        $mailable = self::INVITATION_REVIEWER_ACCESS_INVITE === $this->invitationType
            ? new ReviewerAccessInvitationNotify($this->context, $fakeInvitation)
            : new UserRoleAssignmentInvitationNotify($this->context, $fakeInvitation);

        $sections->addSection(
            new Email(
                'userInvited',
                __('userInvitation.sendMail.stepName'),
                __('userInvitation.sendMail.stepDescription'),
                [],
                $mailable
                    ->sender(Application::get()->getRequest()->getUser())
                    ->cc('')
                    ->bcc(''),
                $this->context->getSupportedFormLocales(),
            ),
            [
                'validateFields' => []
            ]
        );
        $step = new Step(
            'userInvited',
            __('userInvitation.sendMail.stepName'),
            __('userInvitation.sendMail.stepLabel'),
            __('userInvitation.sendMail.nextButtonLabel'),
            'email',
            __('userInvitation.sendMail.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create search user section
     */
    private function invitationReviewDetails(): stdClass
    {
        $sections = new Sections(
            'reviewDetailsForm',
            __('reviewerInvitation.reviewDetails.stepName'),
            'form',
            'ReviewerReviewDetailsFormStep',
            __('reviewerInvitation.reviewDetails.stepDescription'),
        );
        $localeNames = $this->context->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $sections->addSection(
            new Form(
                'userDetails',
                __('reviewerInvitation.reviewDetails.stepName'),
                __('reviewerInvitation.reviewDetails.stepDescription'),
                new ReviewerReviewDetailsForm('reviewers', $locales),
            ),
            [
                'validateFields' => [],
            ]
        );
        $step = new Step(
            'reviewDetails',
            __('reviewerInvitation.reviewDetails.stepName'),
            __('reviewerInvitation.reviewDetails.stepLabel'),
            __('reviewerInvitation.reviewDetails.nextButtonLabel'),
            'form',
            __('reviewerInvitation.reviewDetails.stepDescription'),
            false
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * Get all user groups
     */
    private function getAllUserGroups(): array
    {
        return UserGroup::withContextIds([$this->context->getId()])->get()->values()->toArray();
    }
}
