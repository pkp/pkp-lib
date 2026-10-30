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

use APP\core\Application;
use Illuminate\Support\Str;
use PKP\components\forms\invitation\AcceptUserDetailsForm;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\i18n\LocaleMetadata;
use PKP\invitation\core\Invitation;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\orcid\OrcidManager;
use PKP\orcid\traits\HasOrcidNonce;
use PKP\user\User;

class AcceptInvitationStep extends InvitationStepTypes
{
    use HasOrcidNonce;

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
                if (!$user->hasVerifiedOrcid() && OrcidManager::isEnabled($context)) {
                    $steps[] = $this->verifyOrcidStep($invitation);
                }
                break;
            default:
                if (OrcidManager::isEnabled($context)) {
                    $steps[] = $this->verifyOrcidStep($invitation);
                }
                $steps[] = $this->userAccountDetailsStep();
                $steps[] = $this->userDetailsStep($context);
        }
        $steps[] = $this->acceptInvitationReviewStep($context);
        return $steps;
    }

    /**
     * user orcid verification step
     */
    private function verifyOrcidStep(Invitation $invitation): \stdClass
    {
        $sections = new Sections(
            'userVerifyOrcid',
            __('acceptInvitation.verifyOrcid.stepName'),
            'popup',
            'AcceptInvitationVerifyOrcid',
            __('userInvitation.searchUser.stepDescription'),
        );

        // Create a nonce to pass with ORCID OAuth callback so invitation key remains on server
        $invitationNonce = $this->getCachedNonce((string) $invitation->getId());
        if (!$invitationNonce) {
            $invitationNonce = Str::random(40);
            $this->setCachedValues($invitationNonce, (string) $invitation->getId());
        }

        $orcidOAuthRedirectParams = [
            'targetOp' => 'invitation',
            'invitationNonce' => $invitationNonce,
        ];

        $sections->addSection(
            null,
            [
                'validateFields' => [],
                'orcidUrl' => OrcidManager::getOrcidUrl(),
                'orcidOAuthUrl' => OrcidManager::buildOAuthUrl('authorizeOrcid', $orcidOAuthRedirectParams),
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
     */
    private function getFormLocals(Context $context): array
    {
        $supportedFormLocales = $context->getSupportedFormLocales();
        $sitePrimaryLocale = Application::get()->getRequest()->getSite()->getPrimaryLocale();
        if (!in_array($sitePrimaryLocale, $supportedFormLocales)) {
            $supportedFormLocales[] = $sitePrimaryLocale;
        }
        return collect(Locale::getFormattedDisplayNames($supportedFormLocales, null, LocaleMetadata::LANGUAGE_LOCALE_WITHOUT))
            ->map(fn (string $name, string $locale) => ['key' => $locale, 'label' => $name])
            ->values()
            ->toArray();
    }
}
