<?php

/**
 * @file classes/notification/managerDelegate/PendingRevisionsNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PendingRevisionsNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Pending revision notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\facades\Repo;
use APP\notification\Notification;

use APP\workflow\EditorDecisionActionsManager;
use PKP\db\DAORegistry;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\workflow\WorkflowStageDAO;

class PendingRevisionsNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        return NOTIFICATION_STYLE_CLASS_WARNING;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $submission = Repo::submission()->get($notification->getAssocId());

        $stageData = $this->_getStageDataByType();
        $operation = $stageData['path'];

        return Repo::submission()->getWorkflowUrlByUserRoles($submission, $notification->getUserId(), $stageData['path']);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        $stageData = $this->_getStageDataByType();
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION); // For stage constants
        $stageKey = $stageData['translationKey'];

        return __('notification.type.pendingRevisions', ['stage' => __($stageKey)]);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationContents($request, $notification)
    {
        $stageData = $this->_getStageDataByType();
        $stageId = $stageData['id'];
        $submissionId = $notification->getAssocId();

        $submission = Repo::submission()->get($submissionId);
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);

        import('lib.pkp.controllers.api.file.linkAction.AddRevisionLinkAction');
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR); // editor.review.uploadRevision

        $uploadFileAction = new AddRevisionLinkAction(
            $request,
            $lastReviewRound,
            [Role::ROLE_ID_AUTHOR]
        );

        return $this->fetchLinkActionNotificationContent($uploadFileAction, $request);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationTitle()
     */
    public function getNotificationTitle($notification)
    {
        $stageData = $this->_getStageDataByType();
        $stageKey = $stageData['translationKey'];
        return __('notification.type.pendingRevisions.title', ['stage' => __($stageKey)]);
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification($request, $userIds, $assocType, $assocId)
    {
        $userId = current($userIds);
        $submissionId = $assocId;
        $stageData = $this->_getStageDataByType();
        if ($stageData == null) {
            return;
        }
        $expectedStageId = $stageData['id'];

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /** @var EditDecisionDAO $editDecisionDao */
        $pendingRevisionDecision = $editDecisionDao->findValidPendingRevisionsDecision($submissionId, $expectedStageId, EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS);
        $removeNotifications = false;

        if ($pendingRevisionDecision) {
            if ($editDecisionDao->responseExists($pendingRevisionDecision, $submissionId)) {
                // Some user already uploaded a revision. Flag to delete any existing notification.
                $removeNotifications = true;
            } else {
                $context = $request->getContext();
                $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                $notificationFactory = $notificationDao->getByAssoc(
                    ASSOC_TYPE_SUBMISSION,
                    $submissionId,
                    $userId,
                    PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
                    $context->getId()
                );
                if (!$notificationFactory->next()) {
                    // Create or update a pending revision task notification.
                    $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                    $notificationDao->build(
                        $context->getId(),
                        Notification::NOTIFICATION_LEVEL_TASK,
                        $this->getNotificationType(),
                        ASSOC_TYPE_SUBMISSION,
                        $submissionId,
                        $userId
                    );
                }
            }
        } else {
            // No pending revision decision or other later decision overriden it.
            // Flag to delete any existing notification.
            $removeNotifications = true;
        }

        if ($removeNotifications) {
            $context = $request->getContext();
            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, $userId, $this->getNotificationType(), $context->getId());
            $notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, $userId, PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS, $context->getId());
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Get the data for an workflow stage by
     * pending revisions notification type.
     *
     * @return string
     */
    private function _getStageDataByType()
    {
        $stagesData = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

        switch ($this->getNotificationType()) {
            case PKPNotification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS:
                return $stagesData[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] ?? null;
            case PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS:
                return $stagesData[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] ?? null;
            default:
                assert(false);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\PendingRevisionsNotificationManager', '\PendingRevisionsNotificationManager');
}
