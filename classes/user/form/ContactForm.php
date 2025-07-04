<?php

/**
 * @file classes/user/form/ContactForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContactForm
 *
 * @ingroup user_form
 *
 * @brief Form to edit user's contact information.
 */

namespace PKP\user\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\invitations\changeProfileEmail\ChangeProfileEmailInvite;
use PKP\invitation\models\InvitationModel;
use PKP\user\User;

class ContactForm extends BaseProfileForm
{
    public const ACTION_CANCEL_EMAIL_CHANGE = 'cancelPendingEmail';

    /**
     * Constructor.
     *
     * @param User $user
     */
    public function __construct($user)
    {
        parent::__construct('user/contactForm.tpl', $user);

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom(
            $this,
            'email',
            'required',
            'user.register.form.emailExists',
            function (string $email, int $userId) {
                if ($user = Repo::user()->getByEmail($email, true)) {
                    return (int)$user->getId() === $userId;
                }

                return true;
            },
            [(int)$user->getId()]
        ));
    }

    /**
     * @copydoc BaseProfileForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $site = $request->getSite();
        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[$country->getAlpha2()] = $country->getLocalName();
        }
        asort($countries);
        $templateMgr = TemplateManager::getManager($request);

        $invitationModel = InvitationModel::byType(ChangeProfileEmailInvite::INVITATION_TYPE)
            ->byUserId($this->_user->getId())
            ->stillActive()
            ->first();

        $invitation = new ChangeProfileEmailInvite($invitationModel);

        $templateMgr->assign([
            'countries' => $countries,
            'availableLocales' => $site->getSupportedLocaleNames(),
            'changeEmailPending' => $invitationModel ? $invitation->getPayload()->newEmail : null,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc BaseProfileForm::initData()
     */
    public function initData()
    {
        $user = $this->getUser();

        $this->_data = [
            'country' => $user->getCountry(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'signature' => $user->getSignature(null), // Localized
            'mailingAddress' => $user->getMailingAddress(),
            'affiliation' => $user->getAffiliation(null), // Localized
            'locales' => $user->getLocales(),
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'country', 'email', 'signature', 'phone', 'mailingAddress', 'affiliation', 'locales', 'pendingEmail', 'action',
        ]);

        if ($this->getData('locales') == null || !is_array($this->getData('locales'))) {
            $this->setData('locales', []);
        }
    }

    /**
     * @copydoc Form::execute()
     */
    public function cancelPendingEmail()
    {
        $user = $this->getUser();

        $invitationModel = InvitationModel::byType(ChangeProfileEmailInvite::INVITATION_TYPE)
            ->byUserId($user->getId())
            ->stillActive()
            ->first();

        if ($invitationModel) {
            $invitation = new ChangeProfileEmailInvite($invitationModel);

            $formPendingEmail = $this->getData('pendingEmail');
            if ($invitation->getPayload()->newEmail == $formPendingEmail) {
                $invitationModel->markAs(InvitationStatus::DECLINED);
            }
        }
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $user = $this->getUser();

        // Email will be handled at the parent class code
        if ($user->getEmail() !== $this->getData('email')) {
            // If they are different, store the current email in the array
            $functionArgs['emailUpdated'] = $this->getData('email');
        }

        $user->setCountry($this->getData('country'));
        $user->setSignature($this->getData('signature'), null); // Localized
        $user->setPhone($this->getData('phone'));
        $user->setMailingAddress($this->getData('mailingAddress'));
        $user->setAffiliation($this->getData('affiliation'), null); // Localized

        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $availableLocales = $site->getSupportedLocales();
        $locales = [];
        foreach ($this->getData('locales') as $locale) {
            if (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
                array_push($locales, $locale);
            }
        }
        $user->setLocales($locales);

        parent::execute(...$functionArgs);
    }
}
