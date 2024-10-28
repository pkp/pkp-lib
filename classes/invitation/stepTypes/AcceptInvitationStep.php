<?php
/**
 * @file classes/invitation/stepType/AcceptInvitationStep.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AcceptInvitationStep
 *
 * @brief create accept invitation steps.
 */
namespace PKP\invitation\stepTypes;

use APP\facades\Repo;
use PKP\components\forms\invitation\AcceptUserDetailsForm;
use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\user\User;

class AcceptInvitationStep extends InvitationStepTypes
{
    /**
     * get accept invitation steps
     *
     * @throws \Exception
     */
    public function getSteps(?Invitation $invitation, Context $context, ?User $user): array
    {
        $steps = [];

        switch ($user) {
            case !null:
                if(!$user->getData('orcidAccessToken')) {
                    $steps[] = $this->verifyOrcidStep();
                    $steps[] = $this->acceptInvitationReviewStep($context);
                }
                break;
            default:
                $steps[] = $this->verifyOrcidStep();
                $steps[] = $this->userAccountDetailsStep();
                $steps[] = $this->userDetailsStep($context);
                $steps[] = $this->acceptInvitationReviewStep($context);
        }
        return $steps;
    }

    /**
     * user orcid verification step
     */
    private function verifyOrcidStep(): \stdClass
    {
        $sections = new Sections(
            'userVerifyOrcid',
            __('acceptInvitation.verifyOrcid.stepName'),
            __('userInvitation.searchUser.stepDescription'),
            'popup',
            'AcceptInvitationVerifyOrcid'
        );
        $sections->addSection(
            null,
            [
                'validateFields' => ['userOrcid']
            ]
        );
        $step = new Step(
            'verifyOrcid',
            __('acceptInvitation.verifyOrcid.stepName'),
            __('acceptInvitation.verifyOrcid.stepDescription'),
            __('acceptInvitation.verifyOrcid.stepLabel'),
            __('userInvitation.verifyOrcid.nextButtonLabel'),
            'popup'
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
            __('userInvitation.accountDetails.stepDescription'),
            'form',
            'AcceptInvitationUserAccountDetails'
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
            __('acceptInvitation.accountDetails.stepDescription'),
            __('acceptInvitation.accountDetails.stepLabel'),
            __('acceptInvitation.accountDetails.nextButtonLabel'),
            'form'
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
            __('userInvitation.accountDetails.stepDescription'),
            'form',
            'AcceptInvitationUserDetailsForms'
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('acceptInvitation.userDetails.form.name'),
                __('acceptInvitation.userDetails.form.description'),
                new AcceptUserDetailsForm('accept', $this->getFormLocals($context)),
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
            __('acceptInvitation.userDetails.stepDescription'),
            __('acceptInvitation.userDetails.stepLabel'),
            __('acceptInvitation.userDetails.nextButtonLabel'),
            'form'
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
            '',
            'table',
            'AcceptInvitationReview'
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('acceptInvitation.userDetails.form.name'),
                __('acceptInvitation.userDetails.form.description'),
                new AcceptUserDetailsForm('accept', $this->getFormLocals($context)),
            ),
            [
                'validateFields' => [

                ]
            ]
        );
        $step = new Step(
            'userCreateReview',
            __('acceptInvitation.detailsReview.stepName'),
            __('acceptInvitation.detailsReview.stepDescription'),
            __('acceptInvitation.detailsReview.stepLabel'),
            __('acceptInvitation.detailsReview.nextButtonLabel'),
            'review'
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * @param Context $context
     * @return array
     */
    private function getFormLocals(Context $context): array
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
}
