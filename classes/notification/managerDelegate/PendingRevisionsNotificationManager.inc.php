<?php

/**
 * @file classes/notification/managerDelegate/PendingRevisionsNotificationManager.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PendingRevisionsNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Pending revision notification types manager delegate.
 */

import('lib.pkp.classes.notification.managerDelegate.RevisionsNotificationManager');
import('lib.pkp.classes.workflow.WorkflowStageDAO');

class PendingRevisionsNotificationManager extends RevisionsNotificationManager {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function PendingRevisionsNotificationManager($notificationType) {
		parent::RevisionsNotificationManager($notificationType);
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($notification->getAssocId());

		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $notification->getUserId());

		if ($page == 'workflow') {
			$stageData = $this->_getStageDataByType();
			$operation = $stageData['path'];
		}

		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($submission->getContextId());
		return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submission->getId());
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		$stageData = $this->_getStageDataByType();
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION); // For stage constants
		$stageKey = $stageData['translationKey'];

		return __('notification.type.pendingRevisions', array('stage' => __($stageKey)));
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationContents()
	 */
	public function getNotificationContents($request, $notification) {
		$stageData = $this->_getStageDataByType();
		$stageId = $stageData['id'];
		$submissionId = $notification->getAssocId();

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionId);
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);

		import('lib.pkp.controllers.api.file.linkAction.AddRevisionLinkAction');
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR); // editor.review.uploadRevision

		$uploadFileAction = new AddRevisionLinkAction(
			$request, $lastReviewRound, array(ROLE_ID_AUTHOR)
		);

		return $this->fetchLinkActionNotificationContent($uploadFileAction, $request);
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationTitle()
	 */
	public function getNotificationTitle($notification) {
		$stageData = $this->_getStageDataByType();
		$stageKey = $stageData['translationKey'];
		return __('notification.type.pendingRevisions.title', array('stage' => __($stageKey)));
	}

	/**
	 * @copydoc NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$userId = current($userIds);
		$submissionId = $assocId;
		$stageData = $this->_getStageDataByType();
		$expectedStageId = $stageData['id'];

		$pendingRevisionDecision = $this->findValidPendingRevisionsDecision($submissionId, $expectedStageId);
		$removeNotifications = false;

		if ($pendingRevisionDecision) {
			if ($this->responseExists($pendingRevisionDecision, $submissionId)) {
				// Some user already uploaded a revision. Flag to delete any existing notification.
				$removeNotifications = true;
			} else {
				// Create or update a pending revision task notification.
				$context = $request->getContext();
				$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
				$notificationDao->build(
					$context->getId(),
					NOTIFICATION_LEVEL_TASK,
					$this->getNotificationType(),
					ASSOC_TYPE_SUBMISSION,
					$submissionId,
					$userId
				);
			}
		} else {
			// No pending revision decision or other later decision overriden it.
			// Flag to delete any existing notification.
			$removeNotifications = true;
		}

		if ($removeNotifications) {
			$context = $request->getContext();
			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, $userId, $this->getNotificationType(), $context->getId());
		}
	}


	//
	// Private helper methods.
	//
	/**
	 * Get the data for an workflow stage by
	 * pending revisions notification type.
	 * @return string
	 */
	private function _getStageDataByType() {
		$stagesData = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

		switch ($this->getNotificationType()) {
			case NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS:
				return $stagesData[WORKFLOW_STAGE_ID_INTERNAL_REVIEW];
			case NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
				return $stagesData[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW];
			default:
				assert(false);
		}
	}
}

?>
