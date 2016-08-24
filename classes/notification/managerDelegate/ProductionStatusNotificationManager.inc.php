<?php

/**
 * @file classes/notification/managerDelegate/ProductionStatusNotificationManager.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProductionStatusNotificationManager
 * @ingroup classses_notification_managerDelegate
 *
 * @brief Production status notifications types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class ProductionStatusNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function ProductionStatusNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
				return __('notification.type.assignProductionUser');
			case NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
				return __('notification.type.awaitingRepresentations');
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
			case NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
			case NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
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

		// One way to check if a production user is assigned
		// Get the 'layout assignment' notifications
		$layoutEditorAssigned = false;
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$layoutEditAssignmentNotificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			null,
			NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT,
			$contextId
		);
		// If there are 'layout assignment' notifications
		if (!$layoutEditAssignmentNotificationFactory->wasEmpty()) {
			$layoutEditAssignmentNotifications = $layoutEditAssignmentNotificationFactory->toArray();
			// Check if at least one user is still in the participants list i.e. assigned
			foreach ($layoutEditAssignmentNotifications as $layoutEditAssignmentNotification) {
				$layoutEditorStageAssignmentFactory = $stageAssignmentDao->getBySubmissionAndStageId($submissionId, WORKFLOW_STAGE_ID_PRODUCTION, null, $layoutEditAssignmentNotification->getUserId());
				if (!$layoutEditorStageAssignmentFactory->wasEmpty()) {
					$layoutEditorAssigned = true;
					break;
				}
			}
		}

		// Another way to check if a production user is assigned
		// Get the production discussions
		$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
		$queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_PRODUCTION);

		// Get representations
		$representationDao = Application::getRepresentationDAO();
		$representations = $representationDao->getBySubmissionId($submissionId, $contextId);

		$notificationType = $this->getNotificationType();

		foreach ($editorStageAssignments as $editorStageAssignment) {
			// If there is a representation
			if (!$representations->wasEmpty()) {
				// Remove 'assign a user' and 'awaiting representations' notification
				$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
			} else {
				// If a user is assigned or there is a production discussion
				if ($layoutEditorAssigned || !$queries->wasEmpty()) {
					if ($notificationType == NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
						// Add 'awaiting representations' notification
						$this->_createNotification(
							$request,
							$submissionId,
							$editorStageAssignment->getUserId(),
							$notificationType,
							$contextId
						);
					} elseif ($notificationType == NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
						// Remove 'assign a user' notification
						$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					}
				} else {
					if ($notificationType == NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
						// Add 'assign a user' notification
						$this->_createNotification(
							$request,
							$submissionId,
							$editorStageAssignment->getUserId(),
							$notificationType,
							$contextId
						);
					} elseif ($notificationType == NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
						// Remove 'awaiting representations' notification
						$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					}
				}
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
