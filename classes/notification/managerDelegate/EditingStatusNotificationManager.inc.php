<?php

/**
 * @file classes/notification/managerDelegate/EditingStatusNotificationManager.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditingStatusNotificationManager
 * @ingroup classses_notification_managerDelegate
 *
 * @brief Editing status notifications types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class EditingStatusNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function EditingStatusNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
				return __('notification.type.assignCopyeditors');
			case NOTIFICATION_TYPE_AWAITING_COPYEDITS:
				return __('notification.type.awaitingCopyedits');
		}
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$url = parent::getNotificationUrl($request, $notification);
		$dispatcher = Application::getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());

		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
			case NOTIFICATION_TYPE_AWAITING_COPYEDITS:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
		}
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_INFORMATION;
	}

	/**
	 * @copydoc NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$context = $request->getContext();
		$contextId = $context->getId();

		assert($assocType == ASSOC_TYPE_SUBMISSION);
		$submissionId = $assocId;
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionId);

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$editorStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $submission->getStageId());

		// One way to check if a copyeditor is assigned
		// Get the 'copyedit assignment' notifications
		$copyEditorAssigned = false;
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$copyEditAssignmentNotificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			null,
			NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT,
			$contextId
		);
		// If there are 'copyedit assignment' notifications
		if (!$copyEditAssignmentNotificationFactory->wasEmpty()) {
			$copyEditAssignmentNotifications = $copyEditAssignmentNotificationFactory->toArray();
			// Check if at least one user is still in the participants list i.e. assigned
			foreach ($copyEditAssignmentNotifications as $copyEditAssignmentNotification) {
				$copyEditorStageAssignmentFactory = $stageAssignmentDao->getBySubmissionAndStageId($submissionId, WORKFLOW_STAGE_ID_EDITING, null, $copyEditAssignmentNotification->getUserId());
				if (!$copyEditorStageAssignmentFactory->wasEmpty()) {
					$copyEditorAssigned = true;
					break;
				}
			}
		}

		// Another way to check if a copyeditor is assigned
		// Get the copyediting discussions
		$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
		$queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_EDITING);

		// Get the copyedited files
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile');
		$copyeditedFiles = $submissionFileDao->getLatestRevisions($submissionId, SUBMISSION_FILE_COPYEDIT);

		$notificationType = $this->getNotificationType();

		foreach ($editorStageAssignments as $editorStageAssignment) {
			switch ($submission->getStageId()) {
				case WORKFLOW_STAGE_ID_PRODUCTION:
					// Remove 'assign a copyeditor' and 'awaiting copyedits' notification
					$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					break;
				default:
					if (!empty($copyeditedFiles)) {
						// Remove 'assign a copyeditor' and 'awaiting copyedits' notification
						$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					} else {
						// If a copyeditor is assigned or there is a copyediting discussion
						if ($copyEditorAssigned || !$queries->wasEmpty()) {
							if ($notificationType == NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
								// Add 'awaiting copyedits' notification
								$this->_createNotification(
									$request,
									$submissionId,
									$editorStageAssignment->getUserId(),
									$notificationType,
									$contextId
								);
							} elseif ($notificationType == NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
								// Remove 'assign a copyeditor' notification
								$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
							}
						} else {
							if ($notificationType == NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
								// Add 'assign a copyeditor' notification
								$this->_createNotification(
									$request,
									$submissionId,
									$editorStageAssignment->getUserId(),
									$notificationType,
									$contextId
								);
							} elseif ($notificationType == NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
								// Remove 'awaiting copyedits' notification
								$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
							}
						}
					}
					break;
			}
		}
	}

	//
	// Helper methods.
	//
	/**
	 * Remove a notification.
	 * @param $submissionId int
	 * @param $userId int
	 * @param $notificationType int NOTIFICATION_TYPE_
	 * @param $contextId int
	 */
	function _removeNotification($submissionId, $userId, $notificationType, $contextId) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationDao->deleteByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			$userId,
			$notificationType,
			$contextId
		);
	}

	/**
	 * Create a notification if none exists.
	 * @param $request PKPRequest
	 * @param $submissionId int
	 * @param $userId int
	 * @param $notificationType int NOTIFICATION_TYPE_
	 * @param $contextId int
	 */
	function _createNotification($request, $submissionId, $userId, $notificationType, $contextId) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			$userId,
			$notificationType,
			$contextId
		);
		if ($notificationFactory->wasEmpty()) {
			$notificationMgr = new NotificationManager();
			$notificationMgr->createNotification(
				$request,
				$userId,
				$notificationType,
				$contextId,
				ASSOC_TYPE_SUBMISSION,
				$submissionId,
				NOTIFICATION_LEVEL_NORMAL,
				null,
				true
			);
		}
	}

}

?>
