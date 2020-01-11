<?php

/**
 * @file controllers/grid/users/reviewer/form/EmailReviewerForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmailReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending an email to a user
 */

import('lib.pkp.classes.form.Form');

class EmailReviewerForm extends Form {

	/** @var ReviewAssignment The review assignment to use for this contact */
	var $_reviewAssignment;

	/**
	 * Constructor.
	 * @param $reviewAssignment ReviewAssignment The review assignment to use for this contact.
	 */
	function __construct($reviewAssignment) {
		parent::__construct('controllers/grid/users/reviewer/form/emailReviewerForm.tpl');

		$this->_reviewAssignment = $reviewAssignment;

		$this->addCheck(new FormValidator($this, 'subject', 'required', 'email.subjectRequired'));
		$this->addCheck(new FormValidator($this, 'message', 'required', 'email.bodyRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'subject',
			'message',
		));
	}

	/**
	 * Display the form.
	 * @param $requestArgs array Request parameters to bounce back with the form submission.
	 * @see Form::fetch
	 */
	function fetch($request, $template = null, $display = false, $requestArgs = array()) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($this->_reviewAssignment->getReviewerId());

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'userFullName' => $this->_reviewAssignment->getReviewerFullName(),
			'requestArgs' => $requestArgs,
			'reviewAssignmentId' => $this->_reviewAssignment->getId(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Send the email
	 * @param $submission Submission
	 */
	function execute($submission) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$toUser = $userDao->getById($this->_reviewAssignment->getReviewerId());
		$request = Application::get()->getRequest();
		$fromUser = $request->getUser();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission);

		$email->addRecipient($toUser->getEmail(), $toUser->getFullName());
		$email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());
		$email->setSubject($this->getData('subject'));
		$email->setBody($this->getData('message'));
		$email->assignParams();
		if (!$email->send()) {
			import('classes.notification.NotificationManager');
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
		}
	}
}


