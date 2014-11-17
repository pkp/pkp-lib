<?php

/**
 * @file classes/notification/managerDelegate/signoff/SignoffNotificationManager.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffNotificationManager
 * @ingroup classes_notification_managerDelegate_signoff
 *
 * @brief Base notification manager delegate class that handles with notifications associated with signoffs.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class SignoffNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function SignoffNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	function getNotificationUrl($request, $notification) {
		assert($notification->getAssocType() == ASSOC_TYPE_SIGNOFF);
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoff = $signoffDao->getById($notification->getAssocId());
		assert(is_a($signoff, 'Signoff') && $signoff->getAssocType() == ASSOC_TYPE_SUBMISSION_FILE);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFile = $submissionFileDao->getLatestRevision($signoff->getAssocId());
		assert(is_a($submissionFile, 'SubmissionFile'));

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionFile->getSubmissionId());

		// Get correct page (author dashboard or workflow), based
		// on user roles (if only author, go to author dashboard).
		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);

		// If workflow, get the correct operation (stage).
		if ($page == 'workflow') {
			$stageId = $signoffDao->getStageIdBySymbolic($signoff->getSymbolic());
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$operation = $userGroupDao->getPathFromId($stageId);
		}

		$dispatcher = Application::getDispatcher();
		$context = $request->getContext();
		return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submissionFile->getSubmissionId());
	}
}


