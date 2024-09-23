<?php

/**
 * @file classes/notification/managerDelegate/PKPEditingProductionStatusNotificationManager.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEditingProductionStatusNotificationManager
 *
 * @ingroup classes_notification_managerDelegate
 *
 * @brief Editing and production status notifications types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\query\Query;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submissionFile\SubmissionFile;

class PKPEditingProductionStatusNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR => __('notification.type.assignCopyeditors'),
            Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS => __('notification.type.awaitingCopyedits'),
            Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER => __('notification.type.assignProductionUser'),
            Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS => __('notification.type.awaitingRepresentations'),
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->contextId);

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type for notification!');
                }
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', [$notification->assocId]);
        }
        throw new \Exception('Unmatched notification type!');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $submissionId): void
    {
        if ($assocType != Application::ASSOC_TYPE_SUBMISSION) {
            throw new \Exception('Unexpected assoc type for notification!');
        }

        $submission = Repo::submission()->get($submissionId);
        $contextId = $submission->getData('contextId');

        // Replaces StageAssignmentDAO::getEditorsAssignedToStage
        $editorStageAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withStageIds([$submission->getData('stageId')])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->get();

        // Get the production discussions
        $productionQuery = Query::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->withStageId(WORKFLOW_STAGE_ID_PRODUCTION)
            ->first();

        // Get the copyedited files
        $countCopyeditedFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_COPYEDIT])
            ->getCount();

        // Get representations
        if ($latestPublication = $submission->getLatestPublication()) {
            $representationDao = Application::getRepresentationDAO(); /** @var \PKP\submission\RepresentationDAOInterface $representationDao */
            $representations = $representationDao->getByPublicationId($latestPublication->getId());
        } else {
            $representations = [];
        }

        $notificationType = $this->getNotificationType();

        foreach ($editorStageAssignments as $editorStageAssignment) {
            switch ($submission->getData('stageId')) {
                case WORKFLOW_STAGE_ID_PRODUCTION:
                    if ($notificationType == Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR || $notificationType == Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
                        // Remove 'assign a copyeditor' and 'awaiting copyedits' notification
                        $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                    } else {
                        // If there is a representation
                        if (count($representations)) {
                            // Remove 'assign a production user' and 'awaiting representations' notification
                            $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                        } else {
                            // Remove 'assign a production user' and 'awaiting representations' notification
                            // If a production user is assigned i.e. there is a production discussion
                            if ($productionQuery) {
                                if ($notificationType == Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
                                    // Add 'awaiting representations' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->userId,
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Remove 'assign a production user' notification
                                    $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                                }
                            } else {
                                if ($notificationType == Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Add 'assign a user' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->userId,
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
                                    // Remove 'awaiting representations' notification
                                    $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                                }
                            }
                        }
                    }
                    break;
                case WORKFLOW_STAGE_ID_EDITING:
                    if ($countCopyeditedFiles) {
                        // Remove 'assign a copyeditor' and 'awaiting copyedits' notification
                        $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                    } else {
                        // If a copyeditor is assigned i.e. there is a copyediting discussion
                        $editingQueries = Query::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                            ->withStageId(WORKFLOW_STAGE_ID_EDITING)
                            ->first();
                        if ($editingQueries) {
                            if ($notificationType == Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
                                // Add 'awaiting copyedits' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->userId,
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Remove 'assign a copyeditor' notification
                                $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                            }
                        } else {
                            if ($notificationType == Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Add 'assign a copyeditor' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->userId,
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
                                // Remove 'awaiting copyedits' notification
                                $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
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
     */
    public function _removeNotification(int $submissionId, int $userId, int $notificationType, ?int $contextId): int
    {
        return Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->withUserId($userId)
            ->withType($notificationType)
            ->withContextId($contextId)
            ->delete();
    }

    /**
     * Create a notification if none exists.
     */
    public function _createNotification(PKPRequest $request, int $submissionId, int $userId, int $notificationType, ?int $contextId): void
    {
        $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->withUserId($userId)
            ->withType($notificationType)
            ->withContextId($contextId)
            ->first();
        if (!$notification) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                $request,
                $userId,
                $notificationType,
                $contextId,
                Application::ASSOC_TYPE_SUBMISSION,
                $submissionId
            );
        }
    }
}
