<?php

/**
 * @file classes/notification/managerDelegate/PKPEditingProductionStatusNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEditingProductionStatusNotificationManager
 *
 * @ingroup classses_notification_managerDelegate
 *
 * @brief Editing and productionstatus notifications types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\notification\NotificationDAO;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submissionFile\SubmissionFile;

class PKPEditingProductionStatusNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, PKPNotification $notification): ?string
    {
        return match($notification->getType()) {
            PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR => __('notification.type.assignCopyeditors'),
            PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS => __('notification.type.awaitingCopyedits'),
            PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER => __('notification.type.assignProductionUser'),
            PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS => __('notification.type.awaitingRepresentations'),
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, PKPNotification $notification): ?string
    {
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->getContextId());

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                if ($notification->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    throw new \Exception('Unexpected assoc type for notification!');
                }
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', [$notification->getAssocId()]);
        }
        throw new \Exception('Unmatched notification type!');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(PKPNotification $notification): string
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

        // Get the copyediting and production discussions
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var \PKP\query\QueryDAO $queryDao */
        $productionQueries = $queryDao->getByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_PRODUCTION);
        $productionQuery = $productionQueries->next();

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
                    if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR || $notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
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
                                if ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
                                    // Add 'awaiting representations' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->userId,
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Remove 'assign a production user' notification
                                    $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                                }
                            } else {
                                if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Add 'assign a user' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->userId,
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
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
                        $editingQueries = $queryDao->getByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_EDITING);
                        if ($editingQueries->next()) {
                            if ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
                                // Add 'awaiting copyedits' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->userId,
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Remove 'assign a copyeditor' notification
                                $this->_removeNotification($submissionId, $editorStageAssignment->userId, $notificationType, $contextId);
                            }
                        } else {
                            if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Add 'assign a copyeditor' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->userId,
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
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
    public function _removeNotification(int $submissionId, int $userId, int $notificationType, int $contextId): int
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        return $notificationDao->deleteByAssoc(
            Application::ASSOC_TYPE_SUBMISSION,
            $submissionId,
            $userId,
            $notificationType,
            $contextId
        );
    }

    /**
     * Create a notification if none exists.
     */
    public function _createNotification(PKPRequest $request, int $submissionId, int $userId, int $notificationType, int $contextId): void
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationFactory = $notificationDao->getByAssoc(
            Application::ASSOC_TYPE_SUBMISSION,
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
                Application::ASSOC_TYPE_SUBMISSION,
                $submissionId
            );
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\PKPEditingProductionStatusNotificationManager', '\PKPEditingProductionStatusNotificationManager');
}
