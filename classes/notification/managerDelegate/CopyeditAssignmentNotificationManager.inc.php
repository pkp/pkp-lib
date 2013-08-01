<?php

/**
 * @file classes/notification/managerDelegate/CopyeditAssignmentNotificationManager.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopyeditAssignmentNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Copyedit assignment notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class CopyeditAssignmentNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function CopyeditAssignmentNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @see NotificationManagerDelegate::getNotificationMessage()
	 */
	function getNotificationMessage($request, $notification) {
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoff = $signoffDao->getById($notification->getAssocId());
		assert($signoff->getAssocType() == ASSOC_TYPE_SUBMISSION_FILE);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFile = $submissionFileDao->getLatestRevision($signoff->getAssocId());
		return __('notification.type.copyeditorRequest', array('file' => $submissionFile->getLocalizedName()));
	}

	/**
	 * @see NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */

		// Get the signoff that user needs to signoff.
		$signoff = $signoffDao->getById($assocId);

		// Check if any user has not already signed off the passed signoff.
		$signoffSignoff = $signoffDao->getBySymbolic('SIGNOFF_SIGNOFF', ASSOC_TYPE_SIGNOFF, $assocId);

		$signedOff = false;
		if (is_a($signoffSignoff, 'Signoff') && $signoffSignoff->getDateCompleted()) {
			$signedOff = true;
		}

		// Check for an existing notification.
		$userId = !is_null($userIds) ? current($userIds) : null;

		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationFactory = $notificationDao->getByAssoc(
				ASSOC_TYPE_SIGNOFF,
				$assocId,
				$userId,
				NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT
		);

		if (!$notificationFactory->wasEmpty() && (is_null($signoff) || $signedOff)) {
			// Already signed the sign off or the original signoff
			// is deleted, remove all notifications, no matter for which user.
			$notificationDao->deleteByAssoc(ASSOC_TYPE_SIGNOFF, $assocId, null, NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT);
		} else if ($signoff && !$signedOff && $notificationFactory->wasEmpty() && $userId) {
			// Original signoff still present and signoff on that is not completed,
			// create notification.
			$context = $request->getContext();
			$this->createNotification(
				$request,
				$userId,
				NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT,
				$context->getId(),
				ASSOC_TYPE_SIGNOFF,
				$assocId,
				NOTIFICATION_LEVEL_TASK
			);
		}
	}
}

?>
