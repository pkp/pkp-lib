<?php

/**
 * @file classes/notification/PKPNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 *
 * @ingroup notification
 *
 * @see NotificationDAO
 * @see Notification
 *
 * @brief Class for Notification Manager.
 */

namespace PKP\notification;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\notification\managerDelegate\EditorAssignmentNotificationManager;
use PKP\notification\managerDelegate\EditorDecisionNotificationManager;
use PKP\notification\managerDelegate\EditorialReportNotificationManager;
use PKP\notification\managerDelegate\PendingRevisionsNotificationManager;
use PKP\notification\managerDelegate\PKPEditingProductionStatusNotificationManager;
use PKP\notification\managerDelegate\QueryNotificationManager;
use PKP\notification\managerDelegate\SubmissionNotificationManager;
use PKP\payment\QueuedPaymentDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\workflow\WorkflowStageDAO;

class PKPNotificationManager extends PKPNotificationOperationManager
{
    /**
     * Construct a URL for the notification based on its type and associated object
     *
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, PKPNotification $notification): ?string
    {
        $url = parent::getNotificationUrl($request, $notification);
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->getContextId());

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                assert($notification->getAssocType() == Application::ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
            case PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                assert($notification->getAssocType() == Application::ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                assert($notification->getAssocType() == Application::ASSOC_TYPE_REVIEW_ASSIGNMENT && is_numeric($notification->getAssocId()));
                $reviewAssignment = Repo::reviewAssignment()->get($notification->getAssocId());
                $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
                $operation = $reviewAssignment->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? $workflowStageDao::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW : $workflowStageDao::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW;
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', $operation, $reviewAssignment->getSubmissionId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                $reviewAssignment = Repo::reviewAssignment()->get($notification->getAssocId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', $reviewAssignment->getSubmissionId());
            case PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
                assert($notification->getAssocType() == Application::ASSOC_TYPE_ANNOUNCEMENT);
                $announcement = Repo::announcement()->get($notification->getAssocId());
                $context = $contextDao->getById($announcement->getAssocId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'announcement', 'view', [$notification->getAssocId()]);
            case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                return __('notification.type.configurePaymentMethod');
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                $context = $contextDao->getById($notification->getContextId());
                Application::getPaymentManager($context);
                assert($notification->getAssocType() == Application::ASSOC_TYPE_QUEUED_PAYMENT);
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
    public function getNotificationMessage(PKPRequest $request, PKPNotification $notification): ?string
    {
        switch ($notification->getType()) {
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
                if (is_null($notificationSettings['contents'])) {
                    throw new \Exception('Unexpected empty notification settings contents!');
                }
                return $notificationSettings['contents'];
            case PKPNotification::NOTIFICATION_TYPE_PLUGIN_ENABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginEnabled', $notification->getId());
            case PKPNotification::NOTIFICATION_TYPE_PLUGIN_DISABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginDisabled', $notification->getId());
            case PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                if ($notification->getAssocType() != Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                    throw new \Exception('Unexpected association type!');
                }
                $reviewAssignment = Repo::reviewAssignment()->get($notification->getAssocId());
                $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
                return __('notification.type.reviewerComment', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                if ($notification->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.editorAssign', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
                if($notification->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.copyeditorRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
                if ($notification->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.layouteditorRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                if($notification->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->getAssocId());
                return __('notification.type.indexRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
                return __('notification.type.reviewAssignment');
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                return __('notification.type.reviewAssignmentUpdated');
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
                if($notification->getAssocType() != Application::ASSOC_TYPE_REVIEW_ROUND) {
                    throw new \Exception('Unexpected association type!');
                }
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getById($notification->getAssocId());
                $user = $request->getUser();
                // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
                $isAuthor = StageAssignment::withSubmissionIds([$reviewRound->getSubmissionId()])
                    ->withRoleIds([Role::ROLE_ID_AUTHOR])
                    ->withUserId($user->getId())
                    ->exists();

                return __($reviewRound->getStatusKey($isAuthor));
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                return __('payment.type.publication.required');
            case PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
            case PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REMINDER:
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

                return $delegateResult ?: parent::getNotificationMessage($request, $notification);
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
    public function getNotificationContents(PKPRequest $request, PKPNotification $notification): string|array|null
    {
        $content = parent::getNotificationContents($request, $notification);

        switch ($notification->getType()) {
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

                return $delegateResult ?: $content;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationTitle(PKPNotification $notification): string
    {
        switch ($notification->getType()) {
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

                return $delegateResult ?: parent::getNotificationTitle($notification);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getStyleClass(PKPNotification $notification): string
    {
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
                return $delegateResult ?: parent::getStyleClass($notification);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getIconClass(PKPNotification $notification): string
    {
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
                return $delegateResult ?: parent::getIconClass($notification);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers(int $notificationType, int $assocType, int $assocId): bool
    {
        switch ($notificationType) {
            case PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
            case PKPNotification::NOTIFICATION_TYPE_APPROVE_SUBMISSION:
            case PKPNotification::NOTIFICATION_TYPE_VISIT_CATALOG:
            case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                return true;
            case PKPNotification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                return false;
            default:
                $delegateResult = $this->getByDelegate(
                    $notificationType,
                    $assocType,
                    $assocId,
                    __FUNCTION__,
                    [$notificationType, $assocType, $assocId]
                );
                return $delegateResult ?: parent::isVisibleToAllUsers($notificationType, $assocType, $assocId);
        }
    }

    /**
     * Update notifications by type using a delegate. If you want to be able to use
     * this method to update notifications associated with a certain type, you need
     * to first create a manager delegate and define it in getMgrDelegate() method.
     *
     * @param array $notificationTypes The type(s) of the notification(s) to
     * be updated.
     * @param array|null $userIds The notification user(s) id(s), or null for all.
     * @param int $assocType Application::ASSOC_TYPE_... The notification associated object type.
     * @param int $assocId The notification associated object id.
     */
    final public function updateNotification(PKPRequest $request, array $notificationTypes, ?array $userIds, int $assocType, int $assocId): void
    {
        foreach ($notificationTypes as $type) {
            $managerDelegate = $this->getMgrDelegate($type, $assocType, $assocId);
            if (!$managerDelegate) {
                throw new Exception('Unable to get manager delegate!');
            }
            $managerDelegate->updateNotification($request, $userIds, $assocType, $assocId);
        }
    }

    /**
     * Get all subscribable notification types along with names and their setting type values
     *
     */
    public function getNotificationSettingsMap(): array
    {
        return [
            PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => ['settingName' => 'notificationSubmissionSubmitted',
                'emailSettingName' => 'emailNotificationSubmissionSubmitted',
                'settingKey' => 'notification.type.submissionSubmitted'],
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => ['settingName' => 'notificationEditorAssignmentRequired',
                'emailSettingName' => 'emailNotificationEditorAssignmentRequired',
                'settingKey' => 'notification.type.editorAssignmentTask'],
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
            PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REMINDER => ['settingName' => 'notificationEditorialReminder',
                'emailSettingName' => 'emailNotificationEditorialReminder',
                'settingKey' => 'notification.type.editorialReminder'],
            PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT => ['settingName' => 'notificationEditorialReport',
                'emailSettingName' => 'emailNotificationEditorialReport',
                'settingKey' => 'notification.type.editorialReport']
        ];
    }

    /**
     * Get the stage-level notification type constants for editorial decisions
     *
     * @return int[]
     */
    public function getDecisionStageNotifications(): array
    {
        return [
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION
        ];
    }

    /**
     * Get the notification type for each editor decision
     *
     * @return int One of the Notification::NOTIFICATION_TYPE_ constants
     */
    public function getNotificationTypeByEditorDecision(Decision $decision): ?int
    {
        return match($decision->getData('decision')) {
            Decision::ACCEPT => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
            Decision::EXTERNAL_REVIEW => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
            Decision::PENDING_REVISIONS => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            Decision::RESUBMIT => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
            Decision::NEW_EXTERNAL_ROUND => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND,
            Decision::DECLINE, Decision::INITIAL_DECLINE => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
            Decision::REVERT_DECLINE => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE,
            Decision::SEND_TO_PRODUCTION => Notification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION,
            default => null
        };
    }

    //
    // Protected methods
    //
    /**
     * Get the notification manager delegate based on the passed notification type.
     */
    protected function getMgrDelegate(int $notificationType, int $assocType, int $assocId): ?NotificationManagerDelegate
    {
        switch ($notificationType) {
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                if ($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new SubmissionNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_NEW_QUERY:
            case PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                return new QueryNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new EditorAssignmentNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new EditorDecisionNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new PendingRevisionsNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new PKPEditingProductionStatusNotificationManager($notificationType);
            case PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
                return new EditorialReportNotificationManager($notificationType);
        }
        return null; // No delegate required, let calling context handle null.
    }

    /**
     * Try to use a delegate to retrieve a notification data that's defined
     * by the implementation of the
     */
    protected function getByDelegate(int $notificationType, int $assocType, int $assocId, string $operationName, array $parameters): mixed
    {
        if ($delegate = $this->getMgrDelegate($notificationType, $assocType, $assocId)) {
            return $delegate->$operationName(...$parameters);
        }
        return null;
    }


    //
    // Private helper methods.
    //
    /**
     * Return notification settings.
     */
    private function getNotificationSettings(int $notificationId): ?array
    {
        $notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /** @var NotificationSettingsDAO $notificationSettingsDao */
        return $notificationSettingsDao->getNotificationSettings($notificationId) ?: null;
    }

    /**
     * Helper function to get a translated string from a notification with parameters
     */
    private function _getTranslatedKeyWithParameters(string $key, int $notificationId): string
    {
        $params = $this->getNotificationSettings($notificationId);
        return __($key, $this->getParamsForCurrentLocale($params));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\PKPNotificationManager', '\PKPNotificationManager');
}
