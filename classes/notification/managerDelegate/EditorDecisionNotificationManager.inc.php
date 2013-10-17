<?php

/**
 * @file classes/notification/managerDelegate/EditorDecisionNotificationManager.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Editor decision notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class EditorDecisionNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function EditorDecisionNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @see NotificationManagerDelegate::getNotificationMessage()
	 */
	function getNotificationMessage($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW:
				return __('notification.type.editorDecisionInternalReview');
			case NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
				return __('notification.type.editorDecisionAccept');
			case NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
				return __('notification.type.editorDecisionExternalReview');
			case NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
				return __('notification.type.editorDecisionPendingRevisions');
			case NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
				return __('notification.type.editorDecisionResubmit');
			case NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
				return __('notification.type.editorDecisionDecline');
			case NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
				return __('notification.type.editorDecisionSendToProduction');
			default:
				return null;
		}
	}

	/**
	 * @see NotificationManagerDelegate::getStyleClass()
	 */
	function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_INFORMATION;
	}

	/**
	 * @see NotificationManagerDelegate::getNotificationTitle()
	 */
	function getNotificationTitle($notification) {
		return __('notification.type.editorDecisionTitle');
	}

	/**
	 * @copydoc NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$context = $request->getContext();

		// Get the submitter id.
		$userId = current($userIds);

		// Remove any existing editor decision notifications.
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$assocId,
			$userId,
			null,
			$context->getId()
		);

		$editorDecisionNotificationTypes = $this->_getAllEditorDecisionNotificationTypes();
		while(!$notificationFactory->eof()) {
			$notification = $notificationFactory->next();
			if (in_array($notification->getType(), $editorDecisionNotificationTypes)) {
				$notificationDao->deleteObject($notification);
			}
		}

		// Create the notification.
		$this->createNotification(
			$request,
			$userId,
			$this->getNotificationType(),
			$context->getId(),
			ASSOC_TYPE_SUBMISSION,
			$assocId,
			NOTIFICATION_LEVEL_NORMAL
		);
	}

	//
	// Private helper methods
	//
	/**
	 * Get all notification types corresponding to editor decisions.
	 * @return array
	 */
	function _getAllEditorDecisionNotificationTypes() {
		return array(
			NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
			NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
			NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
			NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
			NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
			NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
			NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION
		);
	}
}

?>
