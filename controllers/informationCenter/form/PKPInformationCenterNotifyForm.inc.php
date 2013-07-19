<?php

/**
 * @file controllers/informationCenter/form/PKPInformationCenterNotifyForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InformationCenterNotifyForm
 * @ingroup informationCenter_form
 *
 * @brief Form to notify a user regarding a file
 */



import('lib.pkp.classes.form.Form');

class PKPInformationCenterNotifyForm extends Form {
	/** @var int The file/submission ID this form is for */
	var $itemId;

	/** @var int The type of item the form is for (used to determine which email template to use) */
	var $itemType;

	/**
	 * Constructor.
	 */
	function PKPInformationCenterNotifyForm($itemId, $itemType) {
		parent::Form('controllers/informationCenter/notify.tpl');
		$this->itemId = $itemId;
		$this->itemType = $itemType;

		$this->addCheck(new FormValidatorListbuilder($this, 'users', 'informationCenter.notify.warning'));
		$this->addCheck(new FormValidator($this, 'message', 'required', 'informationCenter.notify.warning'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Fetch the form.
	 * @see Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		if($this->itemType == ASSOC_TYPE_SUBMISSION) {
			$submissionId = $this->itemId;
		} else {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$submissionFile = $submissionFileDao->getLatestRevision($this->itemId);
			$submissionId = $submissionFile->getSubmissionId();
		}

		$templateMgr->assign('submissionId', $submissionId);
		$templateMgr->assign('itemId', $this->itemId);

		// All stages can choose the default template
		$templateKeys = array('NOTIFICATION_CENTER_DEFAULT');

		// template keys indexed by stageId
		$stageTemplates = $this->_getStageTemplates();

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionId);
		$currentStageId = $submission->getStageId();

		$templateKeys = array_merge($templateKeys, $stageTemplates[$currentStageId]);

		foreach ($templateKeys as $templateKey) {
			$template = $this->_getMailTemplate($submission, $templateKey);
			$template->assignParams(array());
			$templates[$templateKey] = $template->getSubject();
		}

		$templateMgr->assign('templates', $templates);

		// check to see if we were handed a userId from the stage participants grid.  If so,
		// pass that in so the list builder can pre-populate. The Listbuilder validates this.

		$templateMgr->assign('userId', (int) $request->getUserVar('userId'));

		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData($request) {
		$this->readUserVars(array('message', 'users', 'template'));
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		$userData = $this->getData('users');
		ListbuilderHandler::unpack($request, $userData);
	}

	/**
	 * Sends a a notification.
	 * @see Form::execute()
	 */
	function execute($request) {
		return parent::execute($request);
	}

	/**
	 * Prepare an email for each user and send
	 * @see ListbuilderHandler::insertEntry
	 */
	function insertEntry($request, $newRowId) {

		$userDao = DAORegistry::getDAO('UserDAO');
		$application = Application::getApplication();
		$request = $application->getRequest(); // need to do this because the method version is null.
		$fromUser = $request->getUser();

		$submissionDao = Application::getSubmissionDAO();

		if($this->itemType == ASSOC_TYPE_SUBMISSION) {
			$submissionId = $this->itemId;
		} else {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$submissionFile =& $submissionFileDao->getLatestRevision($this->itemId);
			$submissionId = $submissionFile->getSubmissionId();
		}

		$submission =& $submissionDao->getById($submissionId);
		$template = $this->getData('template');

		$email = $this->_getMailTemplate($submission, $template, false);
		$email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());

		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		$dispatcher = $request->getDispatcher();

		foreach ($newRowId as $id) {
			$user = $userDao->getById($id);
			if (isset($user)) {
				$email->addRecipient($user->getEmail(), $user->getFullName());
				$email->setBody($this->getData('message'));
				list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $user->getId());
				$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, null, $page, $operation, array('submissionId' => $submission->getId()));

				// these are for *_REQUEST emails
				$email->assignParams(array(
					// COPYEDIT_REQUEST
					'copyeditorName' => $user->getFullName(),
					'copyeditorUsername' => $user->getUsername(),
					'submissionCopyeditingUrl' => $submissionUrl,
					// LAYOUT_REQUEST
					'layoutEditorName' => $user->getFullName(),
					'submissionUrl' => $submissionUrl,
					'layoutEditorUsername' => $user->getUsername(),
					// LAYOUT_COMPLETE, INDEX_COMPLETE, EDITOR_ASSIGN
					'editorialContactName' => $user->getFullname(),
					// INDEX_REQUEST
					'indexerName' => $user->getFullName(),
					'indexerUsername' => $user->getUsername(),
					// EDITOR_ASSIGN
					'editorUsername' => $user->getUsername(),
				));

				$this->_createNotifications($request, $submission, $user, $template);
				$email->send($request);
				// remove the INDEX_ and LAYOUT_ tasks if a user has sent the appropriate _COMPLETE email
				switch ($template) {
					case 'LAYOUT_COMPLETE':
						$this->_removeUploadTaskNotification($submission, NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $request);
						break;
					case 'INDEX_COMPLETE':
						$this->_removeUploadTaskNotification($submission, NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $request);
						break;
				}
			}
		}
	}

	/**
	 * Delete a signoff
	 * (It was throwing a warning when this was not specified. We just want
	 * client side delete.)
	 */
	function deleteEntry($request, $rowId) {
		return true;
	}

	/**
	 * Internal method to create the necessary notifications, with user validation.
	 * @param PKPRquest $request
	 * @param Submission $submission
	 * @param PKPUser $user
	 * @param string $template
	 */
	function _createNotifications($request, $submission, $user, $template) {

		$currentStageId = $submission->getStageId();
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $submission->getStageId(), null, $user->getId());
		$notificationMgr = new NotificationManager();

		switch ($template) {
			case 'COPYEDIT_REQUEST':
				while ($stageAssignment = $stageAssignments->next()) {
					$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
					if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
						import('classes.submission.SubmissionFile');
						$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
						$submissionFileSignoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
						$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), SUBMISSION_FILE_COPYEDIT);
						foreach ($submissionFiles as $submissionFile) {
							$signoffFactory = $submissionFileSignoffDao->getAllBySymbolic('SIGNOFF_COPYEDITING', $submissionFile->getFileId());
							while ($signoff = $signoffFactory->next()) {
								$notificationMgr->updateNotification(
									$request,
									array(NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT),
									array($user->getId()),
									ASSOC_TYPE_SIGNOFF,
									$signoff->getId()
								);
							}
						}
						return;
					}
				}
				// User not in valid role for this task/notification.
				break;
			case 'LAYOUT_REQUEST':
				while ($stageAssignment = $stageAssignments->next()) {
					$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
					if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
						$this->_addUploadTaskNotification($request, NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $user->getId(), $submission->getId());
						return;
					}
				}
				// User not in valid role for this task/notification.
				break;
			case 'INDEX_REQUEST':
				while ($stageAssignment = $stageAssignments->next()) {
					$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
					if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
						$this->_addUploadTaskNotification($request, NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $user->getId(), $submission->getId());
						return;
					}
				}
				// User not in valid role for this task/notification.
				break;
		}
	}

	/**
	 * Add upload task notifications.
	 * @param $request PKPRequest
	 * @param $type int
	 * @param $userId int
	 * @param $submissionId int
	 */
	private function _addUploadTaskNotification($request, $type, $userId, $submissionId) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			$userId,
			$type
		);

		if ($notificationFactory->wasEmpty()) {
			$context = $request->getContext();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createNotification(
				$request,
				$userId,
				$type,
				$context->getId(),
				ASSOC_TYPE_SUBMISSION,
				$submissionId,
				NOTIFICATION_LEVEL_TASK
			);
		}
	}

	/**
	 * Clear potential tasks that may have been assigned to certain
	 * users on certain stages.  Right now, just LAYOUT uploads on the production stage.
	 * @param Submission $submission
	 * @param int $task
	 * @param PKRequest $request
	 */
	private function _removeUploadTaskNotification($submission, $task, $request) {

		// if this is a submission by a LAYOUT_EDITOR for a submission in production, check
		// to see if there is a task notification for that and if so, clear it.
		$currentStageId = $submission->getStageId();
		$notificationMgr = new NotificationManager();

		if ($currentStageId == WORKFLOW_STAGE_ID_PRODUCTION) {

			$user = $request->getUser();
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $submission->getStageId(), null, $user->getId());

			while ($stageAssignment = $stageAssignments->next()) {
				$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
				if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
					$notificationDao = DAORegistry::getDAO('NotificationDAO');
					$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), $user->getId(), $task);
					return;
				}
			}
		}
	}

	/**
	 * return app-specific stage templates.
	 * @return array
	 */
	protected function _getStageTemplates() {
		assert(false); // Must be overridden in sub classs.
	}

	/**
	 * return app-specific mail template.
	 * @param Submission $submission
	 * @param String $templateKey
	 * @param boolean $includeSignature
	 * @return array
	 */
	protected function _getMailTemplate($submission, $templateKey, $includeSignature = true) {
		assert(false); // Must be overridden in sub classs.
	}
}

?>
