<?php

/**
 * @file classes/notification/managerDelegate/QueryNotificationManager.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Query notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class QueryNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function QueryNotificationManagerDelegate($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotifictionTitle()
	 */
	public function getNotificationTitle($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_NEW_QUERY:
				return 'New query FIXME';
				break;
			case NOTIFICATION_TYPE_QUERY_ACTIVITY:
				return 'Query activity FIXME';
				break;
			default: assert(false);
		}
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		switch($notification->getType()) {
			case NOTIFICATION_TYPE_NEW_QUERY:
				return __('submission.query.new');
			case NOTIFICATION_TYPE_QUERY_ACTIVITY:
				return __('submission.query.activity');
			default: assert(false);
		}
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		assert($notification->getAssocType() == ASSOC_TYPE_QUERY);
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($notification->getAssocId());

		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $notification->getUserId());

		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($submission->getContextId());
		return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submission->getId());
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationContents()
	 */
	public function getNotificationContents($request, $notification) {
		assert($notification->getAssocType() == ASSOC_TYPE_QUERY);
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$query = $queryDao->getById($notification->getAssocId());
		assert(is_a($query, 'Query'));

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($query->getSubmissionId());
		assert(is_a($submission, 'Submission'));

		switch($notification->getType()) {
			case NOTIFICATION_TYPE_NEW_QUERY:
				return __(
					'submission.query.new.contents',
					array(
						'queryTitle' => $query->getHeadNote()->getTitle(),
						'submissionTitle' => $submission->getLocalizedTitle(),
					)
				);
			case NOTIFICATION_TYPE_QUERY_ACTIVITY:
				return __(
					'submission.query.activity.contents',
					array(
						'queryTitle' => $query->getHeadNote()->getTitle(),
						'submissionTitle' => $submission->getLocalizedTitle(),
					)
				);
			default: assert(false);
		}
	}

	/**
	 * @copydoc NotificationManagerDelegate::getStyleClass()
	 */
	public function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_WARNING;
	}

	/**
	 * @copydoc NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$notificationType = $this->getNotificationType();
		if (is_null($notificationType)) {
			return false;
		}

		$context = $request->getContext();
		$contextId = $context->getId();
		assert($userIds == null); // This will handle all users affected.
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$queryDao = DAORegistry::getDAO('QueryDAO');
		assert($assocType == ASSOC_TYPE_QUERY);
		$query = $queryDao->getById($assocId);
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $query->getId());
		switch ($notificationType) {
			case NOTIFICATION_TYPE_NEW_QUERY:
				// Check for an existing notification...
				$notificationFactory = $notificationDao->getByAssoc(
					ASSOC_TYPE_QUERY,
					$query->getId(),
					null, // All users
					$notificationType,
					$contextId
				);
				$notifiedUserIds = array();
				$queryParticipants = $queryDao->getParticipantIds($query->getId());
				while ($notification = $notificationFactory->next()) {
					if ($query->getIsClosed() || !in_array($notification->getUserId(), $queryParticipants)) {
						// Notification exists but query is closed or not assigned; delete notification.
						$notificationDao->deleteObject($notification);
					}
					$notifiedUserIds[] = $notification->getUserId();
				}
				if (!$query->getIsClosed() && $notes->getCount() == 1) {
					foreach (array_diff($queryParticipants, $notifiedUserIds) as $userId) {
						// User needs new notification.
						$this->createNotification(
							$request,
							$userId,
							$notificationType,
							$contextId,
							ASSOC_TYPE_QUERY,
							$query->getId(),
							NOTIFICATION_LEVEL_TASK
						);
					}
				}
				break;
			case NOTIFICATION_TYPE_QUERY_ACTIVITY:
				// Check for an existing notification...
				$notificationFactory = $notificationDao->getByAssoc(
					ASSOC_TYPE_QUERY,
					$query->getId(),
					null, // All users
					$notificationType,
					$contextId
				);
				$notifiedUserIds = array();
				$queryParticipants = $queryDao->getParticipantIds($query->getId());
				while ($notification = $notificationFactory->next()) {
					if ($notification && $query->getIsClosed()) {
						// Notification exists but query is closed; delete notification.
						$notificationDao->deleteObject($notification);
					}
					$notifiedUserIds[] = $notification->getUserId();
				}
				if (!$query->getIsClosed() && $notes->getCount() > 1) {
					foreach (array_diff($queryParticipants, $notifiedUserIds) as $userId) {
						// No notification but query is open; create notification.
						// (Exclude notifications on the head note as a special case.)
						$this->createNotification(
							$request,
							$userId,
							$notificationType,
							$contextId,
							ASSOC_TYPE_QUERY,
							$query->getId(),
							NOTIFICATION_LEVEL_TASK
						);
					}
				}
				break;
			default: assert(false);
		}
	}
}

?>
