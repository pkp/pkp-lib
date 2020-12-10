<?php

/**
 * @file classes/user/form/ContactForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContactForm
 * @ingroup user_form
 *
 * @brief Form to edit user's contact information.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');

class ContactForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $user User
	 */
	function __construct($user) {
		parent::__construct('user/contactForm.tpl', $user);

		// Validation checks for this form
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($user->getId(), true), true));
	}

	/**
	 * @copydoc BaseProfileForm::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$site = $request->getSite();
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'countries' => $countries,
			'availableLocales' => $site->getSupportedLocaleNames(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc BaseProfileForm::initData()
	 */
	function initData() {
		$user = $this->getUser();

		$this->_data = array(
			'country' => $user->getCountry(),
			'email' => $user->getEmail(),
			'phone' => $user->getPhone(),
			'signature' => $user->getSignature(null), // Localized
			'mailingAddress' => $user->getMailingAddress(),
			'affiliation' => $user->getAffiliation(null), // Localized
			'userLocales' => $user->getLocales(),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'country', 'email', 'signature', 'phone', 'mailingAddress', 'affiliation', 'userLocales',
		));

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}

	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
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
		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);

		parent::execute(...$functionArgs);
	}
}


