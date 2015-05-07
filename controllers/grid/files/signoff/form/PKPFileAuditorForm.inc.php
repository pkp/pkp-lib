<?php

/**
 * @file controllers/grid/files/signoff/form/PKPFileAuditorForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFileAuditorForm
 * @ingroup controllers_grid_files_signoff
 *
 * @brief Base form for Signoffs.
 */

import('lib.pkp.classes.form.Form');

class PKPFileAuditorForm extends Form {
	/** The submission associated with the submission contributor being edited **/
	var $_submission;

	/** @var int */
	var $_fileStage;

	/** @var int */
	var $_stageId;

	/** @var string */
	var $_symbolic;

	/** @var string */
	var $_eventType;

	/** @var int */
	var $_assocId;

	/** @var int */
	var $_signoffId;

	/** @var int */
	var $_fileId;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $fileStage int SUBMISSION_FILE_...
	 * @param $stageId int WORKFLOW_STAGE_...
	 * @param $symbolic string Symbolic name of signoff
	 * @param $eventType int
	 * @param $assocId int Optional
	 */
	function PKPFileAuditorForm($submission, $fileStage, $stageId, $symbolic, $eventType, $assocId = null) {
		parent::Form('controllers/grid/files/signoff/form/addAuditor.tpl');
		$this->_submission = $submission;
		$this->_fileStage = $fileStage;
		$this->_stageId = $stageId;
		$this->_symbolic = $symbolic;
		$this->_eventType = $eventType;
		$this->_assocId = $assocId;

		$this->addCheck(new FormValidator($this, 'userId', 'required', 'editor.submission.fileAuditor.form.userRequired'));
		$this->addCheck(new FormValidatorListbuilder($this, 'files', 'editor.submission.fileAuditor.form.fileRequired'));
		$this->addCheck(new FormValidator($this, 'personalMessage', 'required', 'editor.submission.fileAuditor.form.messageRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the file stage.
	 * @return integer
	 */
	function getFileStage() {
		return $this->_fileStage;
	}
	/**
	 * Get the workflow stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the signoff's symbolic
	 * @return string
	 */
	function getSymbolic() {
		return $this->_symbolic;
	}

	/**
	 * Get the email key
	 */
	function getEventType() {
		return $this->_eventType;
	}

	/**
	 * Get the assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->_assocId;
	}

	/**
	 * Get the signoff id that this form creates when executed.
	 * @return int
	 */
	function getSignoffId() {
		return $this->_signoffId;
	}

	/**
	 * Get the file id associated with the signoff
	 * created when this form is executed.
	 * @return int
	 */
	function getFileId() {
		return $this->_fileId;
	}


	//
	// Overridden template methods
	//
	/**
	 * Initialize variables
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$submission = $this->getSubmission();
		$this->setData('submissionId', $submission->getId());
		$this->setData('fileStage', $this->getFileStage());
		$this->setData('assocId', $this->getAssocId());

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, 'AUDITOR_REQUEST');
		$user = $request->getUser();
		// Intentionally omit {$auditorName} for now -- see bug #7090
		$email->assignParams(array(
			'editorialContactSignature' => $user->getContactSignature(),
			'weekLaterDate' => strftime(
				Config::getVar('general', 'date_format_short'),
				time() + 604800 // 60 * 60 * 24 * 7 seconds
			),
		));

		$context = $request->getContext();
		$this->setData('personalMessage', $email->getBody() . "\n" . $context->getSetting('emailSignature'));
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('userId-GroupId', 'files', 'responseDueDate', 'personalMessage', 'skipEmail'));

		list($userId, $userGroupId) = explode('-', $this->getData('userId-GroupId'));
		$this->setData('userId', $userId);
		$this->setData('userGroupId', $userGroupId);
	}

	/**
	 * Assign user to copyedit the selected files
	 * @see Form::execute()
	 */
	function execute($request) {
		// Decode the "files" list
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		ListbuilderHandler::unpack($request, $this->getData('files'));

		// Send the message to the user
		$submission = $this->getSubmission();
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, 'AUDITOR_REQUEST', null, null, null, false);
		$email->setBody($this->getData('personalMessage'));

		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		// FIXME: How to validate user IDs?
		$user = $userDao->getById($this->getData('userId'));
		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $user->getId());

		$dispatcher = $request->getDispatcher();
		$auditUrl = $dispatcher->url($request, ROUTE_PAGE, null, $page, $operation, array('submissionId' => $submission->getId()));

		// Other parameters assigned above; see bug #7090.
		$email->assignParams(array(
			'auditorName' => $user->getFullName(),
			'auditorUserName' => $user->getUsername(),
			'auditUrl' => $auditUrl,
		));

		$email->addRecipient($user->getEmail(), $user->getFullName());
		$email->setEventType($this->getEventType());
		if (!$this->getData('skipEmail')) {
			$email->send($request);
		}
	}

	/**
	 * Persist a signoff insertion
	 * @see ListbuilderHandler::insertEntry
	 */
	function insertEntry($request, $newRowId) {
		// Fetch and validate the file ID
		$fileId = (int) $newRowId['name'];
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFile = $submissionFileDao->getLatestRevision($fileId, null, $submission->getId());
		assert($submissionFile);

		// FIXME: How to validate user IDs?
		$userId = (int) $this->getData('userId');

		// Fetch and validate user group ID
		$userGroupId = (int) $this->getData('userGroupId');
		$context = $request->getContext();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroup = $userGroupDao->getById($userGroupId, $context->getId());

		// Build the signoff.
		$submissionFileSignoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
		$signoff = $submissionFileSignoffDao->build(
			$this->getSymbolic(),
			$submissionFile->getFileId(),
			$userId, $userGroup->getId()
		); /* @var $signoff Signoff */

		// Set the date notified
		$signoff->setDateNotified(Core::getCurrentDate());

		// Set the date response due (stored as date underway in signoffs table)
		$dueDateParts = explode('-', $this->getData('responseDueDate'));
		$signoff->setDateUnderway(date('Y-m-d H:i:s', mktime(0, 0, 0, $dueDateParts[0], $dueDateParts[1], $dueDateParts[2])));
		$submissionFileSignoffDao->updateObject($signoff);

		$this->_signoffId = $signoff->getId();
		$this->_fileId = $signoff->getAssocId();

		$notificationMgr = new NotificationManager();
		$notificationMgr->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_AUDITOR_REQUEST),
			array($signoff->getUserId()),
			ASSOC_TYPE_SIGNOFF,
			$signoff->getId()
		);

		// log the add auditor event.
		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($userId);
		if (isset($user)) {
			SubmissionFileLog::logEvent($request, $submissionFile, SUBMISSION_LOG_FILE_AUDITOR_ASSIGN, 'submission.event.fileAuditorAdded', array('file' => $submissionFile->getOriginalFileName(), 'name' => $user->getFullName(), 'username' => $user->getUsername()));
		}

		$notificationMgr->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF),
			array($signoff->getUserId()),
			ASSOC_TYPE_SUBMISSION,
			$submission->getId()
		);
	}

	/**
	 * Delete a signoff
	 * Noop: we just want client side delete.
	 */
	function deleteEntry($request, $rowId) {
		return true;
	}
}

?>
