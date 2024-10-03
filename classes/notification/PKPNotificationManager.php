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
 * @see Notification
 *
 * @brief Class for Notification Manager.
 */

namespace PKP\notification;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\announcement\Announcement;
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
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        $url = parent::getNotificationUrl($request, $notification);
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->contextId);

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->assocId);
            case Notification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
            case Notification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
            case Notification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->assocId);
            case Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                if ($notification->assocType != Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                    throw new \Exception('Unexpected assoc type!');
                }
                $reviewAssignment = Repo::reviewAssignment()->get($notification->assocId);
                $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
                $operation = $reviewAssignment->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? $workflowStageDao::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW : $workflowStageDao::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW;
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', $operation, [$reviewAssignment->getSubmissionId()]);
            case Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
            case Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                $reviewAssignment = Repo::reviewAssignment()->get($notification->assocId);
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', [$reviewAssignment->getSubmissionId()]);
            case Notification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
                if ($notification->assocType != Application::ASSOC_TYPE_ANNOUNCEMENT) {
                    throw new \Exception('Unexpected assoc type!');
                }
                $announcement = Announcement::find($notification->assocId);
                $context = $contextDao->getById($announcement->assocId);
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'announcement', 'view', [$notification->assocId]);
            case Notification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                return __('notification.type.configurePaymentMethod');
            case Notification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                $context = $contextDao->getById($notification->contextId);
                Application::get()->getPaymentManager($context);
                if ($notification->assocType != Application::ASSOC_TYPE_QUEUED_PAYMENT) {
                    throw new \Exception('Unexpected assoc type!');
                }
                $queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /** @var QueuedPaymentDAO $queuedPaymentDao */
                $queuedPayment = $queuedPaymentDao->getById($notification->assocId);
                $context = $contextDao->getById($queuedPayment->getContextId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'payment', 'pay', [$queuedPayment->getId()]);
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
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
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_SUCCESS:
            case Notification::NOTIFICATION_TYPE_ERROR:
            case Notification::NOTIFICATION_TYPE_WARNING:
                if (!is_null($this->getNotificationSettings($notification->id))) {
                    $notificationSettings = $this->getNotificationSettings($notification->id);
                    return $notificationSettings['contents'];
                } else {
                    return __('common.changesSaved');
                }
                // no break
            case Notification::NOTIFICATION_TYPE_FORM_ERROR:
            case Notification::NOTIFICATION_TYPE_ERROR:
                $notificationSettings = $this->getNotificationSettings($notification->id);
                if (is_null($notificationSettings['contents'])) {
                    throw new \Exception('Unexpected empty notification settings contents!');
                }
                return $notificationSettings['contents'];
            case Notification::NOTIFICATION_TYPE_PLUGIN_ENABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginEnabled', $notification->id);
            case Notification::NOTIFICATION_TYPE_PLUGIN_DISABLED:
                return $this->_getTranslatedKeyWithParameters('common.pluginDisabled', $notification->id);
            case Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT:
                if ($notification->assocType != Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                    throw new \Exception('Unexpected association type!');
                }
                $reviewAssignment = Repo::reviewAssignment()->get($notification->assocId);
                $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
                return __('notification.type.reviewerComment', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGN:
                if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->assocId);
                return __('notification.type.editorAssign', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case Notification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT:
                if($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->assocId);
                return __('notification.type.copyeditorRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case Notification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT:
                if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->assocId);
                return __('notification.type.layouteditorRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case Notification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT:
                if($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected association type!');
                }
                $submission = Repo::submission()->get($notification->assocId);
                return __('notification.type.indexRequest', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]);
            case Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT:
                return __('notification.type.reviewAssignment');
            case Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED:
                return __('notification.type.reviewAssignmentUpdated');
            case Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
                if($notification->assocType != Application::ASSOC_TYPE_REVIEW_ROUND) {
                    throw new \Exception('Unexpected association type!');
                }
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getById($notification->assocId);
                $user = $request->getUser();
                // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
                $isAuthor = StageAssignment::withSubmissionIds([$reviewRound->getSubmissionId()])
                    ->withRoleIds([Role::ROLE_ID_AUTHOR])
                    ->withUserId($user->getId())
                    ->exists();

                return __($reviewRound->getStatusKey($isAuthor));
            case Notification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
                return __('payment.type.publication.required');
            case Notification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
            case Notification::NOTIFICATION_TYPE_EDITORIAL_REMINDER:
                $notificationSettings = $this->getNotificationSettings($notification->id);
                return $notificationSettings['contents'];
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
                return __('notification.type.revertDecline');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
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
    public function getNotificationContents(PKPRequest $request, Notification $notification): string|array|null
    {
        $content = parent::getNotificationContents($request, $notification);

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_FORM_ERROR:
                return join(' ', $content);
            case Notification::NOTIFICATION_TYPE_ERROR:
                if (!is_array($content)) {
                    return $content;
                }

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('errors', $content);
                return $templateMgr->fetch('controllers/notification/errorNotificationContent.tpl');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
                    __FUNCTION__,
                    [$request, $notification]
                );

                return $delegateResult ?: $content;
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationTitle(Notification $notification): string
    {
        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getById($notification->assocId);
                return __('notification.type.roundStatusTitle', ['round' => $reviewRound->getRound()]);
            case Notification::NOTIFICATION_TYPE_FORM_ERROR:
                return __('form.errorsOccurred');
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
                    __FUNCTION__,
                    [$notification]
                );

                return $delegateResult ?: parent::getNotificationTitle($notification);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getStyleClass(Notification $notification): string
    {
        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_SUCCESS: return NOTIFICATION_STYLE_CLASS_SUCCESS;
            case Notification::NOTIFICATION_TYPE_WARNING: return NOTIFICATION_STYLE_CLASS_WARNING;
            case Notification::NOTIFICATION_TYPE_ERROR: return NOTIFICATION_STYLE_CLASS_ERROR;
            case Notification::NOTIFICATION_TYPE_INFORMATION: return NOTIFICATION_STYLE_CLASS_INFORMATION;
            case Notification::NOTIFICATION_TYPE_FORBIDDEN: return NOTIFICATION_STYLE_CLASS_FORBIDDEN;
            case Notification::NOTIFICATION_TYPE_HELP: return NOTIFICATION_STYLE_CLASS_HELP;
            case Notification::NOTIFICATION_TYPE_FORM_ERROR: return NOTIFICATION_STYLE_CLASS_FORM_ERROR;
            case Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS: return NOTIFICATION_STYLE_CLASS_INFORMATION;
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
                    __FUNCTION__,
                    [$notification]
                );
                return $delegateResult ?: parent::getStyleClass($notification);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getIconClass(Notification $notification): string
    {
        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
            case Notification::NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
            case Notification::NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
            case Notification::NOTIFICATION_TYPE_INFORMATION: return 'notifyIconInfo';
            case Notification::NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
            case Notification::NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
            default:
                $delegateResult = $this->getByDelegate(
                    $notification->type,
                    $notification->assocType,
                    $notification->assocId,
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
            case Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS:
            case Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION:
            case Notification::NOTIFICATION_TYPE_VISIT_CATALOG:
            case Notification::NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD:
                return true;
            case Notification::NOTIFICATION_TYPE_PAYMENT_REQUIRED:
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
                throw new \Exception('Unable to get manager delegate!');
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
            Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => ['settingName' => 'notificationSubmissionSubmitted',
                'emailSettingName' => 'emailNotificationSubmissionSubmitted',
                'settingKey' => 'notification.type.submissionSubmitted'],
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => ['settingName' => 'notificationEditorAssignmentRequired',
                'emailSettingName' => 'emailNotificationEditorAssignmentRequired',
                'settingKey' => 'notification.type.editorAssignmentTask'],
            Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT => ['settingName' => 'notificationReviewerComment',
                'emailSettingName' => 'emailNotificationReviewerComment',
                'settingKey' => 'notification.type.reviewerComment'],
            Notification::NOTIFICATION_TYPE_NEW_QUERY => ['settingName' => 'notificationNewQuery',
                'emailSettingName' => 'emailNotificationNewQuery',
                'settingKey' => 'notification.type.queryAdded'],
            Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY => ['settingName' => 'notificationQueryActivity',
                'emailSettingName' => 'emailNotificationQueryActivity',
                'settingKey' => 'notification.type.queryActivity'],
            Notification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT => ['settingName' => 'notificationNewAnnouncement',
                'emailSettingName' => 'emailNotificationNewAnnouncement',
                'settingKey' => 'notification.type.newAnnouncement'],
            Notification::NOTIFICATION_TYPE_EDITORIAL_REMINDER => ['settingName' => 'notificationEditorialReminder',
                'emailSettingName' => 'emailNotificationEditorialReminder',
                'settingKey' => 'notification.type.editorialReminder'],
            Notification::NOTIFICATION_TYPE_EDITORIAL_REPORT => ['settingName' => 'notificationEditorialReport',
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
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION
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
            case Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
            case Notification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                if ($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new SubmissionNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_NEW_QUERY:
            case Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                return new QueryNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION:
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW:
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING:
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new EditorAssignmentNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
            case Notification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new EditorDecisionNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS:
            case Notification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new PendingRevisionsNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                if($assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type!');
                }
                return new PKPEditingProductionStatusNotificationManager($notificationType);
            case Notification::NOTIFICATION_TYPE_EDITORIAL_REPORT:
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
