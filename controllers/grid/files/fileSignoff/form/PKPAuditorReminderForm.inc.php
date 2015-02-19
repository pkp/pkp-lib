<?php

/**
 * @file controllers/grid/files/fileSignoff/form/PKPAuditorReminderForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuditorReminderForm
 * @ingroup controllers_grid_files_fileSignoff_form
 *
 * @brief Base Form for sending a singoff reminder to an auditor.
 */

import('lib.pkp.classes.form.Form');

class PKPAuditorReminderForm extends Form {
	/** The signoff associated with the auditor */
	var $_signoff;

	/** The submission id */
	var $_submissionId;

	/** The current stage id */
	var $_stageId;

	/**
	 * Constructor.
	 */
	function PKPAuditorReminderForm(&$signoff, $submissionId, $stageId) {
		parent::Form('controllers/grid/files/fileSignoff/form/auditorReminderForm.tpl'); // context-specific.
		$this->_signoff = $signoff;
		$this->_submissionId = $submissionId;
		$this->_stageId = $stageId;

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the signoff
	 * @return Signoff
	 */
	function &getSignoff() {
		return $this->_signoff;
	}

	/**
	 * Get the submission id.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->_submissionId;
	}

	/**
	 * Get the stage id.
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $request->getUser();
		$context = $request->getContext();

		$signoff = $this->getSignoff();
		$auditorId = $signoff->getUserId();
		$auditor = $userDao->getById($auditorId);

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->getSubmissionId());

		$email = $this->_getMailTemplate($submission);

		// Format the review due date
		$signoffDueDate = strtotime($signoff->getDateUnderway());
		$dateFormatShort = Config::getVar('general', 'date_format_short');
		if ($signoffDueDate == -1) $signoffDueDate = $dateFormatShort; // Default to something human-readable if no date specified
		else $signoffDueDate = strftime($dateFormatShort, $signoffDueDate);

		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $auditor->getId());

		$dispatcher = $request->getDispatcher();
		$auditUrl = $dispatcher->url($request, ROUTE_PAGE, null, $page, $operation, array('submissionId' => $submission->getId()));

		$paramArray = array(
			'reviewerName' => $auditor->getFullName(),
			'reviewDueDate' => $signoffDueDate,
			'editorialContactSignature' => $user->getContactSignature(),
			'auditorUserName' => $auditor->getUsername(),
			'passwordResetUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'login', 'resetPassword', $auditor->getUsername(), array('confirm' => Validation::generatePasswordResetHash($auditor->getId()))),
			'submissionReviewUrl' => $auditUrl,
			'contextName' => $context->getLocalizedName(),
			'submissionTitle' => $submission->getLocalizedTitle(),
		);
		$email->assignParams($paramArray);

		$this->setData('submissionId', $submission->getId());
		$this->setData('stageId', $this->getStageId());
		$this->setData('signoffId', $signoff->getId());
		$this->setData('signoff', $signoff);
		$this->setData('auditorName', $auditor->getFullName());
		$this->setData('message', $email->getBody() . "\n" . $context->getSetting('emailSignature'));
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('message'));

	}

	/**
	 * Save review assignment
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function execute($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$submissionDao = Application::getSubmissionDAO();

		$signoff = $this->getSignoff();
		$auditorId = $signoff->getUserId();
		$auditor = $userDao->getById($auditorId);
		$submission = $submissionDao->getById($this->getSubmissionId());

		$email = $this->_getMailTemplate($submission);

		$email->addRecipient($auditor->getEmail(), $auditor->getFullName());
		$email->setBody($this->getData('message'));
		$email->send($request);
	}

	/**
	 * Return a context-specific instance of the mail template.
	 * @param Submission the submission.
	 * @return MailTemplate.
	 */
	function _getMailTemplate($submission) {
		assert(false); // overridden in subclasses.
	}
}

?>
