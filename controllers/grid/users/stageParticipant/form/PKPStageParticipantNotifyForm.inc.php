<?php

/**
 * @file lib/pkp/controllers/grid/users/stageParticipant/form/PKPStageParticipantNotifyForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InformationCenterNotifyForm
 * @ingroup informationCenter_form
 *
 * @brief Form to notify a user regarding a file
 */

import('lib.pkp.classes.form.Form');

class PKPStageParticipantNotifyForm extends Form {
	/** @var int The file/submission ID this form is for */
	var $_itemId;

	/** @var int The type of item the form is for (used to determine which email template to use) */
	var $_itemType;

	/** The stage Id **/
	var $_stageId;

	/** @var int the Submission id */
	var $_submissionId;

	/**
	 * Constructor.
	 */
	function PKPStageParticipantNotifyForm($itemId, $itemType, $stageId, $template = null) {
		$template = ($template != null) ? $template : 'controllers/grid/users/stageParticipant/form/notify.tpl';
		parent::Form($template);
		$this->_itemId = $itemId;
		$this->_itemType = $itemType;
		$this->_stageId = $stageId;

		if($itemType == ASSOC_TYPE_SUBMISSION) {
			$this->_submissionId = $itemId;
		} else {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$submissionFile = $submissionFileDao->getLatestRevision($itemId);
			$this->_submissionId = $submissionFile->getSubmissionId();
		}

		// Some other forms (e.g. the Add Participant form) subclass this form and do not have the list builder
		// or may not enforce the sending of an email.  Only include checks if the list builder is included.
		if ($this->includeNotifyUsersListbuilder()) {
			$this->addCheck(new FormValidatorListbuilder($this, 'users', 'stageParticipants.notify.warning'));
			$this->addCheck(new FormValidator($this, 'message', 'required', 'stageParticipants.notify.warning'));
		}
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign('submissionId', $this->_submissionId);
		$templateMgr->assign('itemId', $this->_itemId);

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->_submissionId);

		// All stages can choose the default template
		$templateKeys = array('NOTIFICATION_CENTER_DEFAULT');

		// Determine if the current user can use any custom templates defined.
		$user = $request->getUser();
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$userRoles = $roleDao->getByUserId($user->getId(), $submission->getContextId());
		foreach ($userRoles as $userRole) {
			if (in_array($userRole->getId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
				$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
				$customTemplates = $emailTemplateDao->getCustomTemplateKeys(Application::getContextAssocType(), $submission->getContextId());
				$templateKeys = array_merge($templateKeys, $customTemplates);
				break;
			}
		}

		// template keys indexed by stageId
		$stageTemplates = $this->_getStageTemplates();

		$currentStageId = $this->getStageId();

		if (array_key_exists($currentStageId, $stageTemplates)) {
			$templateKeys = array_merge($templateKeys, $stageTemplates[$currentStageId]);
		}

		foreach ($templateKeys as $templateKey) {
			$template = $this->_getMailTemplate($submission, $templateKey);
			$template->assignParams(array());
			$templates[$templateKey] = $template->getSubject();
		}

		$templateMgr->assign('templates', $templates);
		$templateMgr->assign('stageId', $currentStageId);
		$templateMgr->assign('includeNotifyUsersListbuilder', $this->includeNotifyUsersListbuilder());

		// check to see if we were handed a userId from the stage participants grid.  If so,
		// pass that in so the list builder can pre-populate. The Listbuilder validates this.

		$templateMgr->assign('userId', (int) $request->getUserVar('userId'));

		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData($request) {
		$this->readUserVars(array('message', 'users', 'template'));
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		$userData = $this->getData('users');
		ListbuilderHandler::unpack($request, $userData);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($request) {
		return parent::execute($request);
	}

	/**
	 * @copydoc ListbuilderHandler::insertEntry()
	 */
	function insertEntry($request, $newRowId) {

		$userDao = DAORegistry::getDAO('UserDAO');
		$application = Application::getApplication();
		$request = $application->getRequest(); // need to do this because the method version is null.

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->_submissionId);

		foreach ($newRowId as $id) {
			$this->sendMessage($id, $submission, $request);
		}
	}

	/**
	 * Send a message to a user.
	 * @param $userId int the user id to send email to.
	 * @param $submission Submission
	 * @param $request PKPRequest
	 */
	function sendMessage($userId, $submission, $request) {

		$template = $this->getData('template');
		$fromUser = $request->getUser();

		$email = $this->_getMailTemplate($submission, $template, false);
		$email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());

		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		$dispatcher = $request->getDispatcher();

		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($userId);
		if (isset($user)) {
			$email->addRecipient($user->getEmail(), $user->getFullName());
			$email->setBody($this->getData('message'));
			$submissionUrl = SubmissionsListGridCellProvider::getUrlByUserRoles($request, $submission, $user->getId());

			// Parameters for various emails
			$email->assignParams(array(
				// COPYEDIT_REQUEST, LAYOUT_REQUEST, INDEX_REQUEST
				'participantName' => $user->getFullName(),
				'participantUsername' => $user->getUsername(),
				'submissionUrl' => $submissionUrl,
				// LAYOUT_COMPLETE, INDEX_COMPLETE, EDITOR_ASSIGN
				'editorialContactName' => $user->getFullname(),
				// EDITOR_ASSIGN
				'editorUsername' => $user->getUsername(),
			));

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

	/**
	 * Get the available email template variable names for the given template name.
	 * @param $emailKey string Email template key
	 * @return array
	 */
	function getEmailVariableNames($emailKey) {
		switch ($emailKey) {
			case 'COPYEDIT_REQUEST':
			case 'LAYOUT_REQUEST':
			case 'INDEX_REQUEST':
				return array(
					'participantName' => __('user.name'),
					'participantUsername' => __('user.username'),
					'submissionUrl' => __('common.url'),
				);
			case 'LAYOUT_COMPLETE':
			case 'INDEX_COMPLETE': return array(
				'editorialContactName' => __('user.role.editor'),
			);
			case 'EDITOR_ASSIGN': return array(
				'editorUsername' => __('user.username'),
				'editorialContactName' => __('user.role.editor'),
			);
		}
	}

	/**
	 * Delete an entry
	 */
	function deleteEntry($request, $rowId) {
		// Dummy function; PHP throws a warning when this is not specified.
		// The actual delete is done on the client side.
		return true;
	}

	/**
	 * Get the stage ID
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
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
		$currentStageId = $this->getStageId();
		$notificationMgr = new NotificationManager();

		if ($currentStageId == WORKFLOW_STAGE_ID_PRODUCTION) {

			$user = $request->getUser();
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $this->getStageId(), null, $user->getId());

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
	 * whether or not to include the Notify Users listbuilder  true, by default.
	 * @return boolean
	 */
	function includeNotifyUsersListbuilder() {
		return true;
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
