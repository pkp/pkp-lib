<?php

/**
 * @file classes/notification/managerDelegate/PKPEditingProductionStatusNotificationManager.php
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

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\submissionFile\SubmissionFile;

class PKPEditingProductionStatusNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
                return __('notification.type.assignCopyeditors');
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
                return __('notification.type.awaitingCopyedits');
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
                return __('notification.type.assignProductionUser');
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                return __('notification.type.awaitingRepresentations');
            default:
                assert(false);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $dispatcher = Application::get()->getDispatcher();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->getContextId());

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS:
            case PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER:
            case PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS:
                assert($notification->getAssocType() == Application::ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
            default:
                assert(false);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification($request, $userIds, $assocType, $assocId)
    {
        assert($assocType == Application::ASSOC_TYPE_SUBMISSION);
        $submissionId = $assocId;
        $submission = Repo::submission()->get($submissionId);
        $contextId = $submission->getContextId();

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $editorStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $submission->getStageId());

        // Get the copyediting and production discussions
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
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
            $representationDao = Application::getRepresentationDAO(); /** @var RepresentationDAO $representationDao */
            $representations = $representationDao->getByPublicationId($latestPublication->getId());
        } else {
            $representations = [];
        }

        $notificationType = $this->getNotificationType();

        foreach ($editorStageAssignments as $editorStageAssignment) {
            switch ($submission->getStageId()) {
                case WORKFLOW_STAGE_ID_PRODUCTION:
                    if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR || $notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
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
                                if ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
                                    // Add 'awaiting representations' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->getUserId(),
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Remove 'assign a production user' notification
                                    $this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
                                }
                            } else {
                                if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER) {
                                    // Add 'assign a user' notification
                                    $this->_createNotification(
                                        $request,
                                        $submissionId,
                                        $editorStageAssignment->getUserId(),
                                        $notificationType,
                                        $contextId
                                    );
                                } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS) {
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
                        $editingQueries = $queryDao->getByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId, WORKFLOW_STAGE_ID_EDITING);
                        if ($editingQueries->next()) {
                            if ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
                                // Add 'awaiting copyedits' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->getUserId(),
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Remove 'assign a copyeditor' notification
                                $this->_removeNotification($submissionId, $editorStageAssignment->getUserId(), $notificationType, $contextId);
                            }
                        } else {
                            if ($notificationType == PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR) {
                                // Add 'assign a copyeditor' notification
                                $this->_createNotification(
                                    $request,
                                    $submissionId,
                                    $editorStageAssignment->getUserId(),
                                    $notificationType,
                                    $contextId
                                );
                            } elseif ($notificationType == PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS) {
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
     *
     * @param int $submissionId
     * @param int $userId
     * @param int $notificationType NOTIFICATION_TYPE_
     * @param int $contextId
     */
    public function _removeNotification($submissionId, $userId, $notificationType, $contextId)
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationDao->deleteByAssoc(
            Application::ASSOC_TYPE_SUBMISSION,
            $submissionId,
            $userId,
            $notificationType,
            $contextId
        );
    }

    /**
     * Create a notification if none exists.
     *
     * @param PKPRequest $request
     * @param int $submissionId
     * @param int $userId
     * @param int $notificationType NOTIFICATION_TYPE_
     * @param int $contextId
     */
    public function _createNotification($request, $submissionId, $userId, $notificationType, $contextId)
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
