<?php

/**
 * @file classes/user/form/ContactForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContactForm
 * @ingroup user_form
 *
 * @brief Form to edit user's contact information.
 */

namespace PKP\user\form;

use APP\core\Application;

use PKP\facades\Locale;
use APP\facades\Repo;
use APP\template\TemplateManager;

class ContactForm extends BaseProfileForm
{
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
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists',
            fn($email, $userId) => !($user = Repo::user()->getByEmail($email, true)) || $user->getId() != $userId, [$user->getId()], true
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
        $templateMgr->assign([
            'countries' => $countries,
            'availableLocales' => $site->getSupportedLocaleNames(),
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
            'country', 'email', 'signature', 'phone', 'mailingAddress', 'affiliation', 'locales',
        ]);

        if ($this->getData('locales') == null || !is_array($this->getData('locales'))) {
            $this->setData('locales', []);
        }
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $user = $this->getUser();

        $user->setCountry($this->getData('country'));
        $user->setEmail($this->getData('email'));
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\ContactForm', '\ContactForm');
}
