<?php

/**
 * @file classes/notification/managerDelegate/review/ReviewRoundNotificationManager.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundNotificationManager
 * @ingroup classes_notification_managerDelegate_review
 *
 * @brief Base manager delegate for notifications related to a review round.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

abstract class ReviewRoundNotificationManager extends NotificationManagerDelegate {

	/** @var $reviewRound ReviewRound */
	protected $reviewRound;

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function ReviewRoundNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_WARNING;
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	function getNotificationUrl($request, $notification) {
		$dispatcher = Application::getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());
	
		$reviewRound = $this->getReviewRound($notification->getAssocId());
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($reviewRound->getSubmissionId());
		import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
		list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission, $notification->getUserId());

		if ($page == 'workflow') {
			$stageId = $reviewRound->getStageId();
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$operation = $userGroupDao->getPathFromId($stageId);
		}

		return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submission->getId());		
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	function getNotificationMessage($request, $notification) {
		$localeKey = $this->getMessageLocaleKey();
		$reviewRound = $this->getReviewRound($notification->getAssocId());
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$stagesData = $userGroupDao->getWorkflowStageKeysAndPaths();
		return __($localeKey, array('stage' => __($stagesData[$reviewRound->getStageId()]['translationKey'])));	
	}

	/**
	 * Get a review round object by id.
	 * @param $reviewRoundId int
	 * @return ReviewRound
	 */
	protected function getReviewRound($reviewRoundId) {
		if (!$this->reviewRound || $this->reviewRound->getId() !== $reviewRoundId) {
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$this->reviewRound = $reviewRoundDao->getById($reviewRoundId);
			assert($this->reviewRound instanceof ReviewRound);
		}
		
		return $this->reviewRound;
	}
	
	/**
	 * Get the notification message locale key.
	 * @return string
	 */
	abstract protected function getMessageLocaleKey();
}
