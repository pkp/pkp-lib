<?php

/**
 * @file classes/notification/managerDelegate/PendingRevisionsNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PendingRevisionsNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Pending revision notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\AddRevisionLinkAction;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\workflow\WorkflowStageDAO;

class PendingRevisionsNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_WARNING;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        $submission = Repo::submission()->get($notification->assocId);
        return Repo::submission()->getWorkflowUrlByUserRoles($submission, $notification->userId);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        $stageData = $this->_getStageDataByType();
        $stageKey = $stageData['translationKey'];

        return __('notification.type.pendingRevisions', ['stage' => __($stageKey)]);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationContents()
     */
    public function getNotificationContents(PKPRequest $request, Notification $notification): mixed
    {
        $stageData = $this->_getStageDataByType();
        $stageId = $stageData['id'];
        $submissionId = $notification->assocId;

        $submission = Repo::submission()->get($submissionId);
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);

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
    public function getNotificationTitle(Notification $notification): string
    {
        $stageData = $this->_getStageDataByType();
        $stageKey = $stageData['translationKey'];
        return __('notification.type.pendingRevisions.title', ['stage' => __($stageKey)]);
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $assocId): void
    {
        $userId = current($userIds);
        $submissionId = $assocId;
        $stageData = $this->_getStageDataByType();
        if ($stageData == null) {
            return;
        }
        $expectedStageId = $stageData['id'];

        $pendingRevisionDecision = Repo::decision()->getActivePendingRevisionsDecision($submissionId, $expectedStageId, Decision::PENDING_REVISIONS);
        $removeNotifications = false;

        if ($pendingRevisionDecision) {
            if (Repo::decision()->revisionsUploadedSinceDecision($pendingRevisionDecision, $submissionId)) {
                // Some user already uploaded a revision. Flag to delete any existing notification.
                $removeNotifications = true;
            } else {
                $context = $request->getContext();
                $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                    ->withUserId($userId)
                    ->withType(Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS)
                    ->withContextId($context->getId())
                    ->first();
                if (!$notification) {
                    Repo::notification()->build(
                        $context->getId(),
                        Notification::NOTIFICATION_LEVEL_TASK,
                        $this->getNotificationType(),
                        Application::ASSOC_TYPE_SUBMISSION,
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
            Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                ->withUserId($userId)
                ->withType($this->getNotificationType())
                ->withContextId($context->getId())
                ->delete();
            Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                ->withUserId($userId)
                ->withType(Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS)
                ->withContextId($context->getId())
                ->delete();
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Get the data for an workflow stage by pending revisions notification type.
     */
    private function _getStageDataByType(): ?array
    {
        $stagesData = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

        return match ($this->getNotificationType()) {
            Notification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS => $stagesData[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] ?? null,
            Notification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS => $stagesData[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] ?? null,
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\PendingRevisionsNotificationManager', '\PendingRevisionsNotificationManager');
}
