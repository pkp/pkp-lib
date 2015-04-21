<?php

/**
 * @file classes/user/form/ContactForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user profile.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');

class ContactForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $user PKPUser
	 */
	function ContactForm($user) {
		parent::BaseProfileForm('user/contactForm.tpl', $user);

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($user->getId(), true), true));
	}

	/**
	 * Fetch the form.
	 * @param $request PKPRequest
	 * @return string JSON-encoded form contents.
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$countryDao = DAORegistry::getDAO('CountryDAO');
		$templateMgr->assign(array(
			'countries' => $countryDao->getCountries(),
		));

		return parent::fetch($request);
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		$user = $this->getUser();

		$this->_data = array(
			'country' => $user->getCountry(),
			'email' => $user->getEmail(),
			'phone' => $user->getPhone(),
			'fax' => $user->getFax(),
			'mailingAddress' => $user->getMailingAddress(),
			'affiliation' => $user->getAffiliation(null), // Localized
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'country', 'email', 'phone', 'fax', 'mailingAddress', 'affiliation',
		));
	}

	/**
	 * Save contact settings.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$user = $request->getUser();

		$user->setCountry($this->getData('country'));
		$user->setEmail($this->getData('email'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setAffiliation($this->getData('affiliation'), null); // Localized

		parent::execute($request, $user);
	}
}

?>
