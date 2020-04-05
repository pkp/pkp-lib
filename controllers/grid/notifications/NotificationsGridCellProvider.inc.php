<?php

/**
 * @file controllers/grid/notifications/NotificationsGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationsGridCellProvider
 * @ingroup controllers_grid_notifications
 *
 * @brief Class for a cell provider that can retrieve labels from notifications
 */


import('lib.pkp.classes.controllers.grid.GridCellProvider');
import('lib.pkp.classes.linkAction.request.AjaxAction');

class NotificationsGridCellProvider extends GridCellProvider {

	/**
	 * Get cell actions associated with this row/column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		assert($column->getId() == 'task');

		$templateMgr = TemplateManager::getManager($request);

		$notification = $row->getData();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());

		$notificationMgr = new NotificationManager();
		$router = $request->getRouter();

		$templateMgr->assign(array(
			'notificationMgr'         => $notificationMgr,
			'notification'            => $notification,
			'context'                 => $context,
			'notificationObjectTitle' => $this->_getTitle($notification),
			'message'                 => PKPString::stripUnsafeHtml($notificationMgr->getNotificationMessage($request, $notification)),
		));

		// See if we're working in a multi-context environment
		$user = $request->getUser();
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAvailable($user?$user->getId():null)->toArray();
		$templateMgr->assign('isMultiContext', count($contexts) > 1);

		return array(new LinkAction(
			'details',
			new AjaxAction($router->url(
				$request, null, null, 'markRead',
				null,
				array('redirect' => 1, 'selectedElements' => array($notification->getId()))
			)),
			$templateMgr->fetch('controllers/grid/tasks/task.tpl')
		));
	}


	//
	// Template methods from GridCellProvider
	//
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		assert($column->getId()=='task');

		// The action has the label.
		return array('label' => '');
	}

	/**
	 * Get the title for a notification.
	 * @param $notification Notification
	 * @return string
	 */
	function _getTitle($notification) {
		switch ($notification->getAssocType()) {
			case ASSOC_TYPE_QUEUED_PAYMENT:
				$contextDao = Application::getContextDAO();
				$paymentManager = Application::getPaymentManager($contextDao->getById($notification->getContextId()));
				$queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /* @var $queuedPaymentDao QueuedPaymentDAO */
				$queuedPayment = $queuedPaymentDao->getById($notification->getAssocId());
				if ($queuedPayment) switch ($queuedPayment->getType()) {
					case PAYMENT_TYPE_PUBLICATION:
						$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
						return $submissionDao->getById($queuedPayment->getAssocId())->getLocalizedTitle();
				}
				assert(false);
				return '—';
			case ASSOC_TYPE_ANNOUNCEMENT:
				$announcementId = $notification->getAssocId();
				$announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /* @var $announcementDao AnnouncementDAO */
				$announcement = $announcementDao->getById($announcementId);
				if ($announcement) return $announcement->getLocalizedTitle();
				return null;
			case ASSOC_TYPE_SUBMISSION:
				$submissionId = $notification->getAssocId();
				break;
			case ASSOC_TYPE_SUBMISSION_FILE:
				$fileId = $notification->getAssocId();
				break;
			case ASSOC_TYPE_REVIEW_ASSIGNMENT:
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
				assert(is_a($reviewAssignment, 'ReviewAssignment'));
				$submissionId = $reviewAssignment->getSubmissionId();
				break;
			case ASSOC_TYPE_REVIEW_ROUND:
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
				$reviewRound = $reviewRoundDao->getById($notification->getAssocId());
				assert(is_a($reviewRound, 'ReviewRound'));
				$submissionId = $reviewRound->getSubmissionId();
				break;
			case ASSOC_TYPE_QUERY:
				$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
				$query = $queryDao->getById($notification->getAssocId());
				assert(is_a($query, 'Query'));
				switch ($query->getAssocType()) {
					case ASSOC_TYPE_SUBMISSION:
						$submissionId = $query->getAssocId();
						break;
					case ASSOC_TYPE_REPRESENTATION:
						$representationDao = Application::getRepresentationDAO();
						$representation = $representationDao->getById($query->getAssocId());
						$publication = Services::get('publication')->get($representation->getData('publicationId'));
						$submissionId = $publication->getData('submissionId');
						break;
					default: assert(false);
				}
				break;
			default:
				return '—';
		}

		if (!isset($submissionId) && isset($fileId)) {
			assert(is_numeric($fileId));
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$submissionFile = $submissionFileDao->getLatestRevision($fileId);
			assert(is_a($submissionFile, 'SubmissionFile'));
			$submissionId = $submissionFile->getSubmissionId();
		}
		assert(is_numeric($submissionId));
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission = $submissionDao->getById($submissionId);
		assert(is_a($submission, 'Submission'));

		return $submission->getLocalizedTitle();
	}
}


