<?php

/**
 * @file classes/notification/managerDelegate/SubmissionNotificationManager.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Submission notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class SubmissionNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function SubmissionNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($notification->getAssocId()); /* @var $submission Submission */

		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
				return __('notification.type.submissionSubmitted', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return __('notification.type.metadataModified', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return __('notification.type.editorAssignmentTask');
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				$contextDao = Application::getContextDAO();
				$context = $contextDao->getById($notification->getContextId());
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'submission', $notification->getAssocId());
			default:
				assert(false);
		}
	}

	/**
	 * @see PKPNotificationManager::getIconClass()
	 */
	public function getIconClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return 'notifyIconPageAlert';
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
				return 'notifyIconNewPage';
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return 'notifyIconEdit';
			default:
				assert(false);
		}
	}

	/**
	 * @see PKPNotificationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return NOTIFICATION_STYLE_CLASS_INFORMATION;
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return '';
			default:
				assert(false);
		}
	}
}

?>
