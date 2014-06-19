<?php

/**
 * @file classes/notification/PKPNotificationManager.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */

import('lib.pkp.classes.notification.PKPNotificationOperationManager');
import('lib.pkp.classes.workflow.WorkflowStageDAO');

class PKPNotificationManager extends PKPNotificationOperationManager {
	/**
	 * Constructor.
	 */
	function PKPNotificationManager() {
		parent::PKPNotificationOperationManager();
	}

	/**
	 * Construct a URL for the notification based on its type and associated object
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getNotificationUrl($request, $notification) {
		$dispatcher = Application::getDispatcher();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());

		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_ALL_REVIEWS_IN:
			case NOTIFICATION_TYPE_ALL_REVISIONS_IN:
				assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ROUND && is_numeric($notification->getAssocId()));
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRound = $reviewRoundDao->getById($notification->getAssocId());
				assert(is_a($reviewRound, 'ReviewRound'));

				$submissionDao = Application::getSubmissionDAO();
				$submission = $submissionDao->getById($reviewRound->getSubmissionId());
				import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
				list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);

				if ($page == 'workflow') {
					$stageId = $reviewRound->getStageId();
					$operation = WorkflowStageDAO::getPathFromId($stageId);
				}

				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submission->getId());
			case NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
			case NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
			case NOTIFICATION_TYPE_APPROVE_SUBMISSION:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
			case NOTIFICATION_TYPE_AUDITOR_REQUEST:
			case NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
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
					$operation = WorkflowStageDAO::getPathFromId($stageId);
				}

				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submissionFile->getSubmissionId());
			case NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', $reviewAssignment->getSubmissionId());
			case NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
				assert($notification->getAssocType() == ASSOC_TYPE_ANNOUNCEMENT);
				$announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /* @var $announcementDao AnnouncementDAO */
				$announcement = $announcementDao->getById($notification->getAssocId()); /* @var $announcement Announcement */
				$context = $contextDao->getById($announcement->getAssocId());
				return $dispatcher->url($request, ROUTE_PAGE, null, $context->getPath(), 'index', array($notification->getAssocId()));
			case NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
				return __('notification.type.configurePaymentMethod');
		}

		return $this->getByDelegate(
			$notification->getType(),
			$notification->getAssocType(),
			$notification->getAssocId(),
			__FUNCTION__,
			array($request, $notification)
		);
	}

	/**
	 * Return a message string for the notification based on its type
	 * and associated object.
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getNotificationMessage($request, $notification) {
		$type = $notification->getType();
		assert(isset($type));
		$submissionDao = Application::getSubmissionDAO();

		switch ($type) {
			case NOTIFICATION_TYPE_SUCCESS:
			case NOTIFICATION_TYPE_ERROR:
			case NOTIFICATION_TYPE_WARNING:
				if (!is_null($this->getNotificationSettings($notification->getId()))) {
					$notificationSettings = $this->getNotificationSettings($notification->getId());
					return $notificationSettings['contents'];
				} else {
					return __('common.changesSaved');
				}
			case NOTIFICATION_TYPE_FORM_ERROR:
			case NOTIFICATION_TYPE_ERROR:
				$notificationSettings = $this->getNotificationSettings($notification->getId());
				assert(!is_null($notificationSettings['contents']));
				return $notificationSettings['contents'];
			case NOTIFICATION_TYPE_PLUGIN_ENABLED:
				return $this->_getTranslatedKeyWithParameters('common.pluginEnabled', $notification->getId());
			case NOTIFICATION_TYPE_PLUGIN_DISABLED:
				return $this->_getTranslatedKeyWithParameters('common.pluginDisabled', $notification->getId());
			case NOTIFICATION_TYPE_LOCALE_INSTALLED:
				return $this->_getTranslatedKeyWithParameters('admin.languages.localeInstalled', $notification->getId());
			case NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
				assert($notification->getAssocType() == ASSOC_TYPE_ANNOUNCEMENT);
				return __('notification.type.newAnnouncement');
			case NOTIFICATION_TYPE_ALL_REVIEWS_IN:
			case NOTIFICATION_TYPE_ALL_REVISIONS_IN:
				if ($notification->getType() == NOTIFICATION_TYPE_ALL_REVIEWS_IN) {
					$localeKey = 'notification.type.allReviewsIn';
				} else {
					$localeKey = 'notification.type.allRevisionsIn';
				}

				assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ROUND && is_numeric($notification->getAssocId()));
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRound = $reviewRoundDao->getById($notification->getAssocId());
				assert(is_a($reviewRound, 'ReviewRound'));
				$stagesData = WorkflowStageDAO::getWorkflowStageKeysAndPaths();
				return __($localeKey, array('stage' => __($stagesData[$reviewRound->getStageId()]['translationKey'])));
			case NOTIFICATION_TYPE_APPROVE_SUBMISSION:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				return __('notification.type.approveSubmission');
			case NOTIFICATION_TYPE_REVIEWER_COMMENT:
				assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ASSIGNMENT && is_numeric($notification->getAssocId()));
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
				$submission = $submissionDao->getById($reviewAssignment->getSubmissionId()); /* @var $submission Submission */
				return __('notification.type.reviewerComment', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				$submission = $submissionDao->getById($notification->getAssocId());
				return __('notification.type.layouteditorRequest', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
				assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
				$submission = $submissionDao->getById($notification->getAssocId());
				return __('notification.type.indexRequest', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
				return __('notification.type.reviewAssignment');
			case NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
				assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ROUND && is_numeric($notification->getAssocId()));
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRound = $reviewRoundDao->getById($notification->getAssocId());

				AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR); // load review round status keys.
				return __($reviewRound->getStatusKey());
			default:
				return $this->getByDelegate(
					$notification->getType(),
					$notification->getAssocType(),
					$notification->getAssocId(),
					__FUNCTION__,
					array($request, $notification)
				);
		}
	}

	/**
	 * Using the notification message, construct, if needed, any additional
	 * content for the notification body. If a specific notification type
	 * is not defined, it will return the string from getNotificationMessage
	 * method for that type.
	 * Define a notification type case on this method only if you need to
	 * present more than just text in notification. If you need to define
	 * just a locale key, use the getNotificationMessage method only.
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getNotificationContents($request, $notification) {
		$type = $notification->getType();
		assert(isset($type));
		$notificationMessage = $this->getNotificationMessage($request, $notification);
		$notificationContent = null;

		switch ($type) {
			case NOTIFICATION_TYPE_FORM_ERROR:
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('errors', $notificationMessage);
				return $templateMgr->fetch('controllers/notification/formErrorNotificationContent.tpl');
			case NOTIFICATION_TYPE_ERROR:
				if (is_array($notificationMessage)) {
					$templateMgr->assign('errors', $notificationMessage);
					return $templateMgr->fetch('controllers/notification/errorNotificationContent.tpl');
				} else {
					return $notificationMessage;
				}
			default:
				$notificationContent = $this->getByDelegate(
					$notification->getType(),
					$notification->getAssocType(),
					$notification->getAssocId(),
					__FUNCTION__,
					array($request, $notification)
				);
				break;
		}

		if ($notificationContent) {
			return $notificationContent;
		} else {
			return $notificationMessage;
		}
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getNotificationTitle($notification) {
		$type = $notification->getType();
		assert(isset($type));
		$notificationTitle = null;

		switch ($type) {
			case NOTIFICATION_TYPE_FORM_ERROR:
				return __('form.errorsOccurred');
			default:
				$notificationTitle = $this->getByDelegate(
					$notification->getType(),
					$notification->getAssocType(),
					$notification->getAssocId(),
					__FUNCTION__,
					array($notification)
				);
				break;
		}

		if ($notificationTitle) {
			return $notificationTitle;
		} else {
			return __('notification.notification');
		}
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getStyleClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUCCESS: return NOTIFICATION_STYLE_CLASS_SUCCESS;
			case NOTIFICATION_TYPE_WARNING: return NOTIFICATION_STYLE_CLASS_WARNING;
			case NOTIFICATION_TYPE_ERROR: return NOTIFICATION_STYLE_CLASS_ERROR;
			case NOTIFICATION_TYPE_INFORMATION: return NOTIFICATION_STYLE_CLASS_INFORMATION;
			case NOTIFICATION_TYPE_FORBIDDEN: return NOTIFICATION_STYLE_CLASS_FORBIDDEN;
			case NOTIFICATION_TYPE_HELP: return NOTIFICATION_STYLE_CLASS_HELP;
			case NOTIFICATION_TYPE_FORM_ERROR: return NOTIFICATION_STYLE_CLASS_FORM_ERROR;
			default:
				$notificationStyleClass = $this->getByDelegate(
					$notification->getType(),
					$notification->getAssocType(),
					$notification->getAssocId(),
					__FUNCTION__,
					array($notification)
				);
				break;
		}

		if ($notificationStyleClass) {
			return $notificationStyleClass;
		} else {
			return '';
		}
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getIconClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
			case NOTIFICATION_TYPE_INFORMATION: return 'notifyIconInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
			default:
				$notificationIconClass = $this->getByDelegate(
					$notification->getType(),
					$notification->getAssocType(),
					$notification->getAssocId(),
					__FUNCTION__,
					array($notification)
				);
				break;
		}
		if ($notificationIconClass) {
			return $notificationIconClass;
		} else {
			return 'notifyIconPageAlert';
		}
	}

	/**
	 * @copydoc INotificationInfoProvider::isVisibleToAllUsers()
	 */
	public function isVisibleToAllUsers($notificationType, $assocType, $assocId) {
		switch ($notificationType) {
			case NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
			case NOTIFICATION_TYPE_APPROVE_SUBMISSION:
			case NOTIFICATION_TYPE_VISIT_CATALOG:
			case NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
				$isVisible = true;
				break;
			default:
				$isVisible = $this->getByDelegate(
					$notificationType,
					$assocType,
					$assocId,
					__FUNCTION__,
					array($notificationType, $assocType, $assocId)
				);
				break;
		}

		if (!is_null($isVisible)) {
			return $isVisible;
		} else {
			return false;
		}
	}

	/**
	 * Update notifications by type using a delegate. If you want to be able to use
	 * this method to update notifications associated with a certain type, you need
	 * to first create a manager delegate and define it in getMgrDelegate() method.
	 * @param $request PKPRequest
	 * @param $notificationTypes array The type(s) of the notification(s) to
	 * be updated.
	 * @param $userIds array The notification user(s) id(s).
	 * @param $assocType int The notification associated object type.
	 * @param $assocId int The notification associated object id.
	 * @return mixed Return false if no operation is executed or the last operation
	 * returned value.
	 */
	final public function updateNotification($request, $notificationTypes = array(), $userIds = array(), $assocType, $assocId) {
		$returner = false;
		foreach ($notificationTypes as $type) {
			$managerDelegate = $this->getMgrDelegate($type, $assocType, $assocId);
			if (!is_null($managerDelegate) && is_a($managerDelegate, 'NotificationManagerDelegate')) {
				$returner = $managerDelegate->updateNotification($request, $userIds, $assocType, $assocId);
			} else {
				assert(false);
			}
		}

		return $returner;
	}


	//
	// Protected methods
	//
	/**
	 * Get the notification manager delegate based on the passed notification type.
	 * @param $notificationType int
	 * @param $assocType int
	 * @param $assocId int
	 * @return mixed Null or NotificationManagerDelegate
	 */
	protected function getMgrDelegate($notificationType, $assocType, $assocId) {
		switch ($notificationType) {
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.SubmissionNotificationManager');
				return new SubmissionNotificationManager($notificationType);
			case NOTIFICATION_TYPE_SIGNOFF_COPYEDIT:
			case NOTIFICATION_TYPE_SIGNOFF_PROOF:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.SignoffNotificationManager');
				return new SignoffNotificationManager($notificationType);
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.EditorAssignmentNotificationManager');
				return new EditorAssignmentNotificationManager($notificationType);
			case NOTIFICATION_TYPE_AUDITOR_REQUEST:
				assert($assocType == ASSOC_TYPE_SIGNOFF && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.AuditorRequestNotificationManager');
				return new AuditorRequestNotificationManager($notificationType);
			case NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
				assert($assocType == ASSOC_TYPE_SIGNOFF && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.CopyeditAssignmentNotificationManager');
				return new CopyeditAssignmentNotificationManager($notificationType);
			case NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
			case NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
			case NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
			case NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
			case NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
			case NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.EditorDecisionNotificationManager');
				return new EditorDecisionNotificationManager($notificationType);
			case NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.PendingRevisionsNotificationManager');
				return new PendingRevisionsNotificationManager($notificationType);
			case NOTIFICATION_TYPE_ALL_REVISIONS_IN:
				assert($assocType == ASSOC_TYPE_REVIEW_ROUND && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.AllRevisionsInNotificationManager');
				return new AllRevisionsInNotificationManager($notificationType);
			case NOTIFICATION_TYPE_ALL_REVIEWS_IN:
				assert($assocType == ASSOC_TYPE_REVIEW_ROUND && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.AllReviewsInNotificationManager');
				return new AllReviewsInNotificationManager($notificationType);
			case NOTIFICATION_TYPE_APPROVE_SUBMISSION:
				assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
				import('lib.pkp.classes.notification.managerDelegate.ApproveSubmissionNotificationManager');
				return new ApproveSubmissionNotificationManager($notificationType);
		}
		return null; // No delegate required, let calling context handle null.
	}

	/**
	 * Try to use a delegate to retrieve a notification data that's defined
	 * by the implementation of the
	 * @param $request PKPRequest
	 * @param $notification Notification
	 * @param $operationName string
	 */
	protected function getByDelegate($notificationType, $assocType, $assocId, $operationName, $parameters) {
		$delegate = $this->getMgrDelegate($notificationType, $assocType, $assocId);
		if (is_a($delegate, 'NotificationManagerDelegate')) {
			return call_user_func_array(array($delegate, $operationName), $parameters);
		} else {
			return null;
		}
	}


	//
	// Private helper methods.
	//
	/**
	 * Return notification settings.
	 * @param $notificationId int
	 * @return Array
	 */
	private function getNotificationSettings($notificationId) {
		$notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /* @var $notificationSettingsDao NotificationSettingsDAO */
		$notificationSettings = $notificationSettingsDao->getNotificationSettings($notificationId);
		if (empty($notificationSettings)) {
			return null;
		} else {
			return $notificationSettings;
		}
	}

	/**
	 * Helper function to get a translated string from a notification with parameters
	 * @param $key string
	 * @param $notificationId int
	 * @return String
	 */
	private function _getTranslatedKeyWithParameters($key, $notificationId) {
		$params = $this->getNotificationSettings($notificationId);
		return __($key, $this->getParamsForCurrentLocale($params));
	}
}

?>
