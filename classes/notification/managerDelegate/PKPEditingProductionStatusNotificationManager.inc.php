<?php

/**
 * @file classes/notification/managerDelegate/PKPEditingProductionStatusNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEditingProductionStatusNotificationManager
 * @ingroup classses_notification_managerDelegate
 *
 * @brief Editing and productionstatus notifications types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class PKPEditingProductionStatusNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function __construct($notificationType) {
		parent::__construct($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
				return __('notification.type.assignCopyeditors');
			case NOTIFICATION_TYPE_AWAITING_COPYEDITS:
				return __('notification.type.awaitingCopyedits');
			case NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
				return __('notification.type.assignProductionUser');
			case NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
				return __('notification.type.awaitingRepresentations');
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$dispatcher = Application::get()->getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());

		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
			case NOTIFICATION_TYPE_AWAITING_COPYEDITS:
			case NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
			case NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
			default:
				assert(false);
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
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission = $submissionDao->getById($submissionId);

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$editorStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $submission->getStageId());

		// Get the copyediting and production discussions
		$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
		$productionQueries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_PRODUCTION);
		$productionQuery = $productionQueries->next();

		// Get the copyedited files
		import('lib.pkp.classes.submission.SubmissionFile');
		$countCopyeditedFiles = Services::get('submissionFile')->getCount([
			'submissionIds' => [$submissionId],
			'fileStages' => [SUBMISSION_FILE_COPYEDIT],
		]);

		// Get representations
		if ($latestPublication = $submission->getLatestPublication()) {
			$representationDao = Application::getRepresentationDAO(); /* @var $representationDao RepresentationDAO */
			$representations = $representationDao->getByPublicationId($latestPublication->getId())->toArray();
		} else {
			$representations = [];
		}

		$notificationType = $this->getNotificationType();

		foreach ($editorStageAssignments as $editorStageAssignment) {
			switch ($submission->getStageId()) {
				case WORKFLOW_STAGE_ID_PRODUCTION:
					if ($notificationType == NOTIFICATION_TYPE_ASSIGN_COPYEDITOR || $notificationType == NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
						// Remove 'assign a copyeditor' and 'awaiting copyedits' notification
						$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					} else {
						// If there is a representation
						if (count($representations)) {
							// Remove 'assign a production user' and 'awaiting representations' notification
							$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
						} else {
							// Remove 'assign a production user' and 'awaiting representations' notification
							// If a production user is assigned i.e. there is a production discussion
							if ($productionQuery) {
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
									// Remove 'assign a production user' notification
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
					break;
				case WORKFLOW_STAGE_ID_EDITING:
					if ($countCopyeditedFiles) {
						// Remove 'assign a copyeditor' and 'awaiting copyedits' notification
						$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
					} else {
						// If a copyeditor is assigned i.e. there is a copyediting discussion
						$editingQueries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_EDITING);
						if ($editingQueries->next()) {
							if ($notificationType == NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
								// Add 'awaiting copyedits' notification
								$this->_createNotification(
									$request,
									$submissionId,
									$editorStageAssignment->getUserId(),
									$notificationType,
									$contextId
								);
							} elseif ($notificationType == NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
								// Remove 'assign a copyeditor' notification
								$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
							}
						} else {
							if ($notificationType == NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
								// Add 'assign a copyeditor' notification
								$this->_createNotification(
									$request,
									$submissionId,
									$editorStageAssignment->getUserId(),
									$notificationType,
									$contextId
								);
							} elseif ($notificationType == NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
								// Remove 'awaiting copyedits' notification
								$this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
							}
						}
					}
					break;
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
		if (!$notificationFactory->next()) {
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


