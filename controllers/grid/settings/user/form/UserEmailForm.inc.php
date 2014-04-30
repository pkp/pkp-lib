<?php

/**
 * @file controllers/grid/settings/user/form/UserEmailForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserEmailForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for sending an email to a user
 */

import('lib.pkp.classes.form.Form');

class UserEmailForm extends Form {

	/* @var the user id of user to send email to */
	var $userId;

	/**
	 * Constructor.
	 */
	function UserEmailForm($userId) {
		parent::Form('controllers/grid/settings/user/form/userEmailForm.tpl');

		$this->userId = (int) $userId;

		$this->addCheck(new FormValidator($this, 'subject', 'required', 'email.subjectRequired'));
		$this->addCheck(new FormValidator($this, 'message', 'required', 'email.bodyRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData($args, $request) {
		$fromUser = $request->getUser();
		$fromSignature = "\n\n\n" . $fromUser->getLocalizedSignature();

		$this->_data = array(
			'message' => $fromSignature
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(
			array(
				'subject',
				'message'
			)
		);

	}

	/**
	 * Display the form.
	 */
	function display($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($this->userId);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('userId', $this->userId);
		$templateMgr->assign('userFullName', $user->getFullName());
		$templateMgr->assign('userEmail', $user->getEmail());

		return $this->fetch($request);
	}

	/**
	 * Send the email
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function execute($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$toUser = $userDao->getById($this->userId);
		$fromUser = $request->getUser();

		import('lib.pkp.classes.mail.MailTemplate');
		$email = new MailTemplate();

		$email->addRecipient($toUser->getEmail(), $toUser->getFullName());
		$email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());
		$email->setSubject($this->getData('subject'));
		$email->setBody($this->getData('message'));
		$email->send();
	}
}

?>
