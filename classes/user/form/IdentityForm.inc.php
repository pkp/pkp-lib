<?php

/**
 * @file classes/user/form/IdentityForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user's identity information.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');

class IdentityForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function __construct($user) {
		parent::__construct('user/identityForm.tpl', $user);

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
	}

	/**
	 * Fetch the form.
	 * @param $request PKPRequest
	 * @param $template string the template to be rendered, mandatory
	 *  if no template has been specified on class instantiation.
	 * @param $display boolean
	 * @return string JSON-encoded form contents.
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);

		$user = $this->getUser();
		$userDao = DAORegistry::getDAO('UserDAO');
		$templateMgr->assign(array(
			'username' => $user->getUsername(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$user = $this->getUser();

		$this->_data = array(
			'salutation' => $user->getSalutation(),
			'firstName' => $user->getFirstName(),
			'middleName' => $user->getMiddleName(),
			'initials' => $user->getInitials(),
			'lastName' => $user->getLastName(),
			'suffix' => $user->getSuffix(),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'salutation', 'firstName', 'middleName', 'initials', 'lastName', 'suffix',
		));
	}

	/**
	 * Save identity settings.
	 */
	function execute($request) {
		$user = $request->getUser();

		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setInitials($this->getData('initials'));
		$user->setLastName($this->getData('lastName'));
		$user->setSuffix($this->getData('suffix'));

		parent::execute($request, $user);
	}
}

?>
