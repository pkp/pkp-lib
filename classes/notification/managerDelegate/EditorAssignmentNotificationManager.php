<?php

/**
 * @file classes/notification/managerDelegate/EditorAssignmentNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAssignmentNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Editor assignment notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class EditorAssignmentNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage($notification)
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        return match($notification->type) {
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_INTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW => __('notification.type.editorAssignment'),
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING => __('notification.type.editorAssignmentEditing'),
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION => __('notification.type.editorAssignmentProduction'),
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_WARNING;
    }

    /**
     * @copydoc PKPNotificationOperationManager::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers(int $notificationType, int $assocType, int $assocId): bool
    {
        return true;
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     *
     * If we have a stage without a manager role user, then
     * a notification must be inserted or maintained for the submission.
     * If a user with this role is assigned to the stage, the notification
     * should be deleted.
     * Every user that have access to the stage should see the notification.
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $assocId): void
    {
        $context = $request->getContext();
        if ($assocType != Application::ASSOC_TYPE_SUBMISSION) {
            throw new \Exception('Unexpected assoc type!');
        }

        // Check for an existing NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_...
        $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $assocId)
            ->withType($this->getNotificationType())
            ->withContextId($context->getId())
            ->first();

        // Check for editor stage assignment.
        // Replaces StageAssignmentDAO::editorAssignedToStage
        $editorAssigned = StageAssignment::withSubmissionIds([$assocId])
            ->withStageIds([$this->_getStageIdByNotificationType()])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->exists();

        // Decide if we have to create or delete a notification.
        if ($editorAssigned && $notification) {
            // Delete the notification.
            $notification->delete();
        } elseif (!$editorAssigned && !$notification) {
            // Create a notification.
            $this->createNotification(
                $request,
                null,
                $this->getNotificationType(),
                $context->getId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $assocId,
                Notification::NOTIFICATION_LEVEL_TASK
            );
        }
    }


    //
    // Helper methods.
    //
    /**
     * Return the correct stage id based on the notification type.
     */
    public function _getStageIdByNotificationType(): int
    {
        return match($this->getNotificationType()) {
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION => WORKFLOW_STAGE_ID_SUBMISSION,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_INTERNAL_REVIEW => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING => WORKFLOW_STAGE_ID_EDITING,
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION => WORKFLOW_STAGE_ID_PRODUCTION,
            default => null
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorAssignmentNotificationManager', '\EditorAssignmentNotificationManager');
}
