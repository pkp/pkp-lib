<?php

/**
 * @file classes/notification/PKPNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 * @ingroup notification
 *
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */

namespace PKP\notification;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\notification\managerDelegate\EditorAssignmentNotificationManager;
use PKP\notification\managerDelegate\EditorDecisionNotificationManager;
use PKP\notification\managerDelegate\EditorialReportNotificationManager;
use PKP\notification\managerDelegate\PendingRevisionsNotificationManager;
use PKP\notification\managerDelegate\PKPEditingProductionStatusNotificationManager;

use PKP\notification\managerDelegate\QueryNotificationManager;
use PKP\notification\managerDelegate\SubmissionNotificationManager;
use PKP\security\Role;

class PKPNotificationManager extends PKPNotificationOperationManager
{
    /**
     * Construct a URL for the notification based on its type and associated object
     *
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationUrl($request, $notification)
    {
        $url = parent::getNotificationUrl($request, $notification);
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->getContextId());

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
            case PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ASSIGNMENT && is_numeric($notification->getAssocId()));
                $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                $reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
                $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
                $operation = $reviewAssignment->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? $workflowStageDao::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW : $workflowStageDao::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW;
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', $operation, $reviewAssignment->getSubmissionId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                $reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', $reviewAssignment->getSubmissionId());
            case PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_ANNOUNCEMENT);
                $announcement = Repo::announcement()->get($notification->getAssocId());
                $context = $contextDao->getById($announcement->getAssocId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'announcement', 'view', [$notification->getAssocId()]);
            case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                return __('notification.type.configurePaymentMethod');
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                $context = $contextDao->getById($notification->getContextId());
                Application::getPaymentManager($context);
                assert($notification->getAssocType() == ASSOC_TYPE_QUEUED_PAYMENT);
                $queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /** @var QueuedPaymentDAO $queuedPaymentDao */
                $queuedPayment = $queuedPaymentDao->getById($notification->getAssocId());
                $context = $contextDao->getById($queuedPayment->getContextId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'payment', 'pay', [$queuedPayment->getId()]);
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$request, $notification]
                );

                if ($delegateResult) {
                    $url = $delegateResult;
                }

                return $url;
        }
    }

    /**
     * Return a message string for the notification based on its type
     * and associated object.
     *
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationMessage($request, $notification)
    {
        $message = parent::getNotificationMessage($request, $notification);
        $type = $notification->getType();
        assert(isset($type));

        switch ($type) {
            case PKPNotification::NOTIFICATION_TYPE_SUCCESS:
            case PKPNotification::NOTIFICATION_TYPE_ERROR:
            case PKPNotification::NOTIFICATION_TYPE_WARNING:
                if (!is_null($this->getNotificationSettings($notification->getId()))) {
                    $notificationSettings = $this->getNotificationSettings($notification->getId());
                    return $notificationSettings['contents'];
                } else {
                    return __('common.changesSaved');
                }
                // no break
            case PKPNotification::NOTIFICATION_TYPE_FORM_ERROR:
            case PKPNotification::NOTIFICATION_TYPE_ERROR:
                $notificationSettings = $this->getNotificationSettings($notification->getId());
                assert(!is_null($notificationSettings['contents']));
                return $notificationSettings['contents'];
            case PKPNotification::NOTIFICATION_TYPE_PLUGIN_ENABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginEnabled', $notification->getId());
            case PKPNotification::NOTIFICATION_TYPE_PLUGIN_DISABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginDisabled', $notification->getId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ASSIGNMENT && is_numeric($notification->getAssocId()));
                $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                $reviewAssignment = $reviewAssignmentDao->getById($notification->getAssocId());
                $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
                return __('notification.type.reviewerComment', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.editorAssign', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.copyeditorRequest', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.layouteditorRequest', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.indexRequest', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
                return __('notification.type.reviewAssignment');
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                return __('notification.type.reviewAssignmentUpdated');
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
                assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ROUND && is_numeric($notification->getAssocId()));
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getById($notification->getAssocId());
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $user = $request->getUser();
                $stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($reviewRound->getSubmissionId(), Role::ROLE_ID_AUTHOR, null, $user->getId());
                $isAuthor = (bool) $stageAssignments->next();
                return __($reviewRound->getStatusKey($isAuthor));
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                return __('payment.type.publication.required');
            case PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
                $notificationSettings = $this->getNotificationSettings($notification->getId());
                return $notificationSettings['contents'];
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
                return __('notification.type.revertDecline');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$request, $notification]
                );

                if ($delegateResult) {
                    $message = $delegateResult;
                }

                return $message;
        }
    }

    /**
     * Using the notification message, construct, if needed, any additional
     * content for the notification body. If a specific notification type
     * is not defined, it will return the parent method return value.
     * Define a notification type case on this method only if you need to
     * present more than just text in notification. If you need to define
     * just a locale key, use the getNotificationMessage method only.
     *
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationContents($request, $notification)
    {
        $content = parent::getNotificationContents($request, $notification);
        $type = $notification->getType();
        assert(isset($type));

        switch ($type) {
            case PKPNotification::NOTIFICATION_TYPE_FORM_ERROR:
                return join(' ', $content);
            case PKPNotification::NOTIFICATION_TYPE_ERROR:
                if (!is_array($content)) {
                    return $content;
                }

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('errors', $content);
                return $templateMgr->fetch('controllers/notification/errorNotificationContent.tpl');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$request, $notification]
                );

                if ($delegateResult) {
                    $content = $delegateResult;
                }
                return $content;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationTitle($notification)
    {
        $title = parent::getNotificationTitle($notification);
        $type = $notification->getType();
        assert(isset($type));

        switch ($type) {
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getById($notification->getAssocId());
                return __('notification.type.roundStatusTitle', ['round' => $reviewRound->getRound()]);
            case PKPNotification::NOTIFICATION_TYPE_FORM_ERROR:
                return __('form.errorsOccurred');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$notification]
                );

                if ($delegateResult) {
                    $title = $delegateResult;
                }
                return $title;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getStyleClass($notification)
    {
        $styleClass = parent::getStyleClass($notification);
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_SUCCESS: return NOTIFICATION_STYLE_CLASS_SUCCESS;
            case PKPNotification::NOTIFICATION_TYPE_WARNING: return NOTIFICATION_STYLE_CLASS_WARNING;
            case PKPNotification::NOTIFICATION_TYPE_ERROR: return NOTIFICATION_STYLE_CLASS_ERROR;
            case PKPNotification::NOTIFICATION_TYPE_INFORMATION: return NOTIFICATION_STYLE_CLASS_INFORMATION;
            case PKPNotification::NOTIFICATION_TYPE_FORBIDDEN: return NOTIFICATION_STYLE_CLASS_FORBIDDEN;
            case PKPNotification::NOTIFICATION_TYPE_HELP: return NOTIFICATION_STYLE_CLASS_HELP;
            case PKPNotification::NOTIFICATION_TYPE_FORM_ERROR: return NOTIFICATION_STYLE_CLASS_FORM_ERROR;
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS: return NOTIFICATION_STYLE_CLASS_INFORMATION;
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$notification]
                );
                if ($delegateResult) {
                    $styleClass = $delegateResult;
                }
                return $styleClass;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getIconClass($notification)
    {
        $iconClass = parent::getIconClass($notification);
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
            case PKPNotification::NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
            case PKPNotification::NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
            case PKPNotification::NOTIFICATION_TYPE_INFORMATION: return 'notifyIconInfo';
            case PKPNotification::NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
            case PKPNotification::NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->getType(),
                    $notification->getAssocType(),
                    $notification->getAssocId(),
                    __FUNCTION__,
                    [$notification]
                );
                if ($delegateResult) {
                    $iconClass = $delegateResult;
                }
                return $iconClass;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers($notificationType, $assocType, $assocId)
    {
        $isVisible = parent::isVisibleToAllUsers($notificationType, $assocType, $assocId);
        switch ($notificationType) {
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
            case PKPNotification::NOTIFICATION_TYPE_APPROVE_SUBMISSION:
            case PKPNotification::NOTIFICATION_TYPE_VISIT_CATALOG:
            case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                $isVisible = true;
                break;
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                $isVisible = false;
                break;
            default:
                $delegateResult = $this->getByDelegate(
                    $notificationType,
                    $assocType,
                    $assocId,
                    __FUNCTION__,
                    [$notificationType, $assocType, $assocId]
                );
                if (!is_null($delegateResult)) {
                    $isVisible = $delegateResult;
                }
                break;
        }
        return $isVisible;
    }

    /**
     * Update notifications by type using a delegate. If you want to be able to use
     * this method to update notifications associated with a certain type, you need
     * to first create a manager delegate and define it in getMgrDelegate() method.
     *
     * @param PKPRequest $request
     * @param array $notificationTypes The type(s) of the notification(s) to
     * be updated.
     * @param array|null $userIds The notification user(s) id(s), or null for all.
     * @param int $assocType ASSOC_TYPE_... The notification associated object type.
     * @param int $assocId The notification associated object id.
     *
     * @return mixed Return false if no operation is executed or the last operation
     * returned value.
     */
    final public function updateNotification($request, $notificationTypes, $userIds, $assocType, $assocId)
    {
        $returner = false;
        foreach ($notificationTypes as $type) {
            $managerDelegate = $this->getMgrDelegate($type, $assocType, $assocId);
            if (!is_null($managerDelegate) && $managerDelegate instanceof \PKP\notification\NotificationManagerDelegate) {
                $returner = $managerDelegate->updateNotification($request, $userIds, $assocType, $assocId);
            } else {
                assert(false);
            }
        }

        return $returner;
    }

    /**
     * Get all subscribable notification types along with names and their setting type values
     *
     * @return array
     */
    public function getNotificationSettingsMap()
    {
        return [
            PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => ['settingName' => 'notificationSubmissionSubmitted',
                'emailSettingName' => 'emailNotificationSubmissionSubmitted',
                'settingKey' => 'notification.type.submissionSubmitted'],
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => ['settingName' => 'notificationEditorAssignmentRequired',
                'emailSettingName' => 'emailNotificationEditorAssignmentRequired',
                'settingKey' => 'notification.type.editorAssignmentTask'],
            PKPNotification::NOTIFICATION_TYPE_METADATA_MODIFIED => ['settingName' => 'notificationMetadataModified',
                'emailSettingName' => 'emailNotificationMetadataModified',
                'settingKey' => 'notification.type.metadataModified'],
            PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT => ['settingName' => 'notificationReviewerComment',
                'emailSettingName' => 'emailNotificationReviewerComment',
                'settingKey' => 'notification.type.reviewerComment'],
            PKPNotification::NOTIFICATION_TYPE_NEW_QUERY => ['settingName' => 'notificationNewQuery',
                'emailSettingName' => 'emailNotificationNewQuery',
                'settingKey' => 'notification.type.queryAdded'],
            PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY => ['settingName' => 'notificationQueryActivity',
                'emailSettingName' => 'emailNotificationQueryActivity',
                'settingKey' => 'notification.type.queryActivity'],
            PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT => ['settingName' => 'notificationNewAnnouncement',
                'emailSettingName' => 'emailNotificationNewAnnouncement',
                'settingKey' => 'notification.type.newAnnouncement'],
            PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT => ['settingName' => 'notificationEditorialReport',
                'emailSettingName' => 'emailNotificationEditorialReport',
                'settingKey' => 'notification.type.editorialReport']
        ];
    }

    //
    // Protected methods
    //
    /**
     * Get the notification manager delegate based on the passed notification type.
     *
     * @param int $notificationType
     * @param int $assocType
     * @param int $assocId
     *
     * @return mixed Null or NotificationManagerDelegate
     */
    protected function getMgrDelegate($notificationType, $assocType, $assocId)
    {
        switch ($notificationType) {
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
            case PKPNotification::NOTIFICATION_TYPE_METADATA_MODIFIED:
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new SubmissionNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_NEW_QUERY:
            case PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                return new QueryNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new EditorAssignmentNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new EditorDecisionNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new PendingRevisionsNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new PKPEditingProductionStatusNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
                return new EditorialReportNotificationManager($notificationType);
        }
        return null; // No delegate required, let calling context handle null.
    }

    /**
     * Try to use a delegate to retrieve a notification data that's defined
     * by the implementation of the
     *
     * @param string $operationName
     */
    protected function getByDelegate($notificationType, $assocType, $assocId, $operationName, $parameters)
    {
        $delegate = $this->getMgrDelegate($notificationType, $assocType, $assocId);
        if ($delegate instanceof \PKP\notification\NotificationManagerDelegate) {
            return call_user_func_array([$delegate, $operationName], $parameters);
        } else {
            return null;
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Return notification settings.
     *
     * @param int $notificationId
     *
     * @return array
     */
    private function getNotificationSettings($notificationId)
    {
        $notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /** @var NotificationSettingsDAO $notificationSettingsDao */
        $notificationSettings = $notificationSettingsDao->getNotificationSettings($notificationId);
        if (empty($notificationSettings)) {
            return null;
        } else {
            return $notificationSettings;
        }
    }

    /**
     * Helper function to get a translated string from a notification with parameters
     *
     * @param string $key
     * @param int $notificationId
     *
     * @return string
     */
    private function _getTranslatedKeyWithParameters($key, $notificationId)
    {
        $params = $this->getNotificationSettings($notificationId);
        return __($key, $this->getParamsForCurrentLocale($params));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\PKPNotificationManager', '\PKPNotificationManager');
}
