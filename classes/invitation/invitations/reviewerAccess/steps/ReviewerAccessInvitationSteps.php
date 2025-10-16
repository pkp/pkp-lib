<?php

namespace PKP\invitation\invitations\reviewerAccess\steps;

use APP\core\Application;
use PKP\components\forms\invitation\AcceptUserDetailsForm;
use PKP\components\forms\invitation\UserDetailsForm;
use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationSteps;
use PKP\invitation\invitations\reviewerAccess\forms\ReviewerReviewDetailsForm;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\sections\Email;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\mail\mailables\ReviewerAccessInvitationNotify;
use PKP\orcid\OrcidManager;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class ReviewerAccessInvitationSteps implements InvitationSteps
{

    public function getSendSteps(?Invitation $invitation, Context $context, ?User $user): array
    {
        $steps = [];
        if ((!$invitation->getId()  && !$user)) {
            $steps[] = $this->invitationSearchUser();
        }
        $steps[] = $this->invitationDetailsForm($context);
        $steps[] = $this->invitationReviewDetails($context);
        $steps[] = $this->invitationInvitedEmail($context);
        return $steps;
    }

    public function getAcceptSteps(Invitation $invitation, Context $context, User $user): array
    {
        $steps = [];
        switch ($user) {
            case !null:
                if(!$user->hasVerifiedOrcid() && OrcidManager::isEnabled($context)) {
                    $steps[] = $this->verifyOrcidStep();
                }
                break;
            default:
                if (OrcidManager::isEnabled($context)) {
                    $steps[] = $this->verifyOrcidStep();
                }
                $steps[] = $this->userAccountDetailsStep();
                $steps[] = $this->userDetailsStep($context);
        }
        $steps[] = $this->acceptInvitationReviewStep($context);
        return $steps;
    }

    /**
     * create search user section
     */
    private function invitationSearchUser(): \stdClass
    {
        $sections = new Sections(
            'searchUserForm',
            __('reviewerInvitation.searchUser.stepName'),
            'form',
            'UserInvitationSearchFormStep',
        );
        $sections->addSection(
            null,
            [
                'validateFields' => []
            ]
        );
        $step = new Step(
            'searchUser',
            __('reviewerInvitation.searchUser.stepName'),
            __('reviewerInvitation.searchUser.stepLabel'),
            __('reviewerInvitation.searchUser.nextButtonLabel'),
            'emptySection',
            '',
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
    private function invitationDetailsForm(Context $context): \stdClass
    {
        $localeNames = $context->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $sections = new Sections(
            'userDetails',
            __('reviewerInvitation.enterDetails.stepName'),
            'form',
            'UserInvitationDetailsFormStep',
            __('reviewerInvitation.enterDetails.stepDescription'),
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('reviewerInvitation.enterDetails.stepName'),
                __('reviewerInvitation.enterDetails.stepDescription'),
                new UserDetailsForm('users', $locales),
            ),
            [
                'validateFields' => [],
                'userGroups' => $this->getAllUserGroups($context),
            ]
        );
        $step = new Step(
            'userDetails',
            __('reviewerInvitation.enterDetails.stepName'),
            __('reviewerInvitation.enterDetails.stepLabel'),
            __('userInvitation.enterDetails.nextButtonLabel'),
            'form',
            __('reviewerInvitation.enterDetails.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create email composer for send invite
     *
     * @throws Exception
     */
    private function invitationInvitedEmail(Context $context): \stdClass
    {
        $sections = new Sections(
            'userInvitedEmail',
            __('userInvitation.sendMail.stepLabel'),
            'email',
            'UserInvitationEmailComposerStep',
            __('userInvitation.sendMail.stepName'),
        );
        $fakeInvitation = $this->getFakeInvitation();
        $mailable = new ReviewerAccessInvitationNotify($context, $fakeInvitation);

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
                $context->getSupportedFormLocales(),
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
    private function invitationReviewDetails(Context $context): \stdClass
    {
        $sections = new Sections(
            'reviewDetailsForm',
            __('reviewerInvitation.reviewDetails.stepName'),
            'form',
            'ReviewerReviewDetailsFormStep',
            __('reviewerInvitation.reviewDetails.stepDescription'),
        );
        $localeNames = $context->getSupportedFormLocaleNames();
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
     * user orcid verification step
     */
    private function verifyOrcidStep(): \stdClass
    {
        $sections = new Sections(
            'userVerifyOrcid',
            __('acceptInvitation.verifyOrcid.stepName'),
            'popup',
            'AcceptInvitationVerifyOrcid',
            __('userInvitation.searchUser.stepDescription'),
        );
        $sections->addSection(
            null,
            [
                'validateFields' => ['orcid', 'orcidIsVerified', 'orcidAccessDenied', 'orcidAccessToken', 'orcidAccessScope', 'orcidRefreshToken', 'orcidAccessExpiresOn'],
                'orcidUrl' => OrcidManager::getOrcidUrl(),
                'orcidOAuthUrl' => OrcidManager::buildOAuthUrl('authorizeOrcid', ['targetOp' => 'invitation']),
            ]
        );
        $step = new Step(
            'verifyOrcid',
            __('acceptInvitation.verifyOrcid.stepName'),
            __('acceptInvitation.verifyOrcid.stepLabel'),
            __('userInvitation.verifyOrcid.nextButtonLabel'),
            'popup',
            __('acceptInvitation.verifyOrcid.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * user account details step
     */
    private function userAccountDetailsStep(): \stdClass
    {
        $sections = new Sections(
            'userCreateForm',
            __('acceptInvitation.accountDetails.stepName'),
            'form',
            'AcceptInvitationUserAccountDetails',
            __('userInvitation.accountDetails.stepDescription'),
        );
        $sections->addSection(
            null,
            [
                'validateFields' => [
                    'username',
                    'password',
                    'privacyStatement'
                ]
            ]
        );
        $step = new Step(
            'userCreate',
            __('acceptInvitation.accountDetails.stepName'),
            __('acceptInvitation.accountDetails.stepLabel'),
            __('acceptInvitation.accountDetails.nextButtonLabel'),
            'form',
            __('acceptInvitation.accountDetails.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * user details form step
     *
     * @throws \Exception
     */
    private function userDetailsStep(Context $context): \stdClass
    {
        $sections = new Sections(
            'userCreateForm',
            __('acceptInvitation.accountDetails.stepName'),
            'form',
            'AcceptInvitationUserDetailsForms',
            __('userInvitation.accountDetails.stepDescription'),
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('acceptInvitation.userDetails.form.name'),
                __('acceptInvitation.userDetails.form.description'),
                new AcceptUserDetailsForm('accept', $this->getFormLocales($context)),
            ),
            [
                'validateFields' => [
                    'affiliation',
                    'givenName',
                    'familyName',
                    'userCountry',
                ]
            ]
        );
        $step = new Step(
            'userDetails',
            __('acceptInvitation.userDetails.stepName'),
            __('acceptInvitation.userDetails.stepLabel'),
            __('acceptInvitation.userDetails.nextButtonLabel'),
            'form',
            __('acceptInvitation.userDetails.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * review details and accept invitation step
     *
     * @throws \Exception
     */
    private function acceptInvitationReviewStep(Context $context): \stdClass
    {
        $sections = new Sections(
            'userCreateRoles',
            '',
            'table',
            'AcceptInvitationReview',
            ''
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('acceptInvitation.userDetails.form.name'),
                __('acceptInvitation.userDetails.form.description'),
                new AcceptUserDetailsForm('accept', $this->getFormLocales($context)),
            ),
            [
                'validateFields' => [

                ]
            ]
        );
        $step = new Step(
            'userCreateReview',
            __('acceptInvitation.detailsReview.stepName'),
            __('acceptInvitation.detailsReview.stepLabel'),
            __('acceptInvitation.detailsReview.nextButtonLabel'),
            'review',
            __('acceptInvitation.detailsReview.stepDescription'),
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * Get all user groups
     */
    private function getAllUserGroups(Context $context): array
    {
        return UserGroup::withContextIds([$context->getId()])->get()->values()->toArray();
    }

    /**
     * Get all form locals
     * @param Context $context
     * @return array
     */
    private function getFormLocales(Context $context): array
    {
        $localeNames = $context->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        return $locales;
    }

    /**
     * fake invitation for email template
     */
    protected function getFakeInvitation(): ReviewerAccessInvite
    {
        return new ReviewerAccessInvite();
    }
}
