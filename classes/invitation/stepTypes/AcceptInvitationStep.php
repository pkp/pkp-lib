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

use PKP\components\forms\invitation\AcceptUserDetailsForm;
use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\orcid\OrcidManager;
use PKP\user\User;

class AcceptInvitationStep extends InvitationStepTypes
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
     * get accept invitation steps
     * @throws \Exception
     */
    public function getSteps(): array
    {
        $steps = [];
        switch ($this->user) {
            case !null:
                if(!$this->user->hasVerifiedOrcid() && OrcidManager::isEnabled($this->context)) {
                    $steps[] = $this->verifyOrcidStep();
                }
                break;
            default:
                if (OrcidManager::isEnabled($this->context)) {
                    $steps[] = $this->verifyOrcidStep();
                }
                $steps[] = $this->userAccountDetailsStep();
                $steps[] = $this->userDetailsStep();
        }
        $steps[] = $this->acceptInvitationReviewStep();
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
    private function userDetailsStep(): \stdClass
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
                new AcceptUserDetailsForm('accept', $this->getFormLocals($this->context)),
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
    private function acceptInvitationReviewStep(): \stdClass
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
                new AcceptUserDetailsForm('accept', $this->getFormLocals($this->context)),
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
     * Get all form locals
     * @param Context $context
     * @return array
     */
    private function getFormLocals(): array
    {
        $localeNames = $this->context->getSupportedFormLocaleNames();
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
