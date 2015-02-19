<?php

/**
 * @file classes/notification/managerDelegate/SignoffNotificationManager.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Signoff notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class SignoffNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function SignoffNotificationManagerDelegate($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	public function getNotificationTitle($notification) {
		return __('notification.type.signoff');
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationMessage($notification)
	 */
	public function getNotificationMessage($request, $notification) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);
		return __('submission.upload.signoff');
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationContents()
	 */
	public function getNotificationContents($request, $notification) {
		$notificationMessage = $this->getNotificationMessage($request, $notification);
		switch($notification->getType()) {
			case NOTIFICATION_TYPE_SIGNOFF_COPYEDIT:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return $this->_getSignoffNotificationContents($request, $notification, 'SIGNOFF_COPYEDITING', $notificationMessage);
			case NOTIFICATION_TYPE_SIGNOFF_PROOF:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return $this->_getSignoffNotificationContents($request, $notification, 'SIGNOFF_PROOFING', $notificationMessage);
		}
	}

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
		$submissionId = $assocId;
		$userId = current($userIds);

		// Check for an existing NOTIFICATION_TYPE_SIGNOFF_...
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$submissionId,
			$userId,
			$notificationType,
			$contextId
		);

		// Check for any active signoff with the $symbolic value.
		$symbolic = $this->_getSymbolicByType();
		$submissionFileSignOffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
		$signoffFactory = $submissionFileSignOffDao->getAllBySubmission($submissionId, $symbolic, $userId);
		$activeSignoffs = false;
		if (!$signoffFactory->wasEmpty()) {
			// Loop through signoffs and check for active ones on this context.
			while (!$signoffFactory->eof()) {
				$workingSignoff = $signoffFactory->next();
				if (!$workingSignoff->getDateCompleted()) {
					$activeSignoffs = true;
					break;
				}
			}
		}

		// Decide if we need to create or delete a notification.
		if (!$activeSignoffs && !$notificationFactory->wasEmpty()) {
			// No signoff but found notification, delete it.
			$notification = $notificationFactory->next();
			$notificationDao->deleteObject($notification);
		} else if ($activeSignoffs && $notificationFactory->wasEmpty()) {
			// At least one signoff not completed and no notification, create one.
			$this->createNotification(
				$request,
				$userId,
				$notificationType,
				$contextId,
				ASSOC_TYPE_SUBMISSION,
				$submissionId,
				NOTIFICATION_LEVEL_TASK
			);
		}
	}


	//
	// Helper methods.
	//
	/**
	 * Get signoff notification type contents.
	 * @param $request Request
	 * @param $notification Notification
	 * @param $symbolic String The signoff symbolic name.
	 * @param $message String The notification message.
	 * @return string
	 */
	private function _getSignoffNotificationContents($request, $notification, $symbolic, $message) {
		$submissionId = $notification->getAssocId();

		// Get the stage id, based on symbolic.
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$stageId = $signoffDao->getStageIdBySymbolic($symbolic);

		import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
		$signoffFileLinkAction = new AddSignoffFileLinkAction(
			$request, $submissionId,
			$stageId, $symbolic, null,
			$message, $message
		);

		return $this->fetchLinkActionNotificationContent($signoffFileLinkAction, $request);
	}

	/**
	 * Get signoff symbolic by notification type.
	 * @return string
	 */
	private function _getSymbolicByType() {
		switch ($this->getNotificationType()) {
			case NOTIFICATION_TYPE_SIGNOFF_COPYEDIT:
				return 'SIGNOFF_COPYEDITING';
			case NOTIFICATION_TYPE_SIGNOFF_PROOF:
				return 'SIGNOFF_PROOFING';
			default:
				return null;
		}
	}
}

?>
