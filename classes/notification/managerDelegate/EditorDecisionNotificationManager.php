<?php

/**
 * @file classes/notification/managerDelegate/EditorDecisionNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Editor decision notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;

class EditorDecisionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW => __('notification.type.editorDecisionInternalReview'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT => __('notification.type.editorDecisionAccept'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW => __('notification.type.editorDecisionExternalReview'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS => __('notification.type.editorDecisionPendingRevisions'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => __('notification.type.editorDecisionResubmit'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND => __('notification.type.editorDecisionNewRound'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE => __('notification.type.editorDecisionDecline'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE => __('notification.type.editorDecisionRevertDecline'),
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION => __('notification.type.editorDecisionSendToProduction'),
            default => null
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationTitle()
     */
    public function getNotificationTitle($notification): string
    {
        return __('notification.type.editorDecisionTitle');
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $assocId): void
    {
        if ($assocType != Application::ASSOC_TYPE_SUBMISSION) {
            throw new \Exception('Unexpected assoc type!');
        }

        // Remove any existing editor decision notifications.
        $notifications = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $assocId)
            ->withContextId($request->getContext()->getId())
            ->get();

        // Delete old notifications.
        $editorDecisionNotificationTypes = $this->_getAllEditorDecisionNotificationTypes();
        foreach ($notifications as $notification) {
            // If a list of user IDs was specified, make sure we're respecting it.
            if ($userIds !== null && !in_array($notification->userId, $userIds)) {
                continue;
            }

            // Check that the notification type is in the specified list.
            if (!in_array($notification->type, $editorDecisionNotificationTypes)) {
                continue;
            }

            $notification->delete();
        }

        // (Re)create notifications, but don’t send email, since we
        // got here from the editor decision which sends its own email.
        foreach ((array) $userIds as $userId) {
            $this->createNotification(
                $request,
                $userId,
                $this->getNotificationType(),
                $request->getContext()->getId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $assocId,
                $this->_getNotificationTaskLevel($this->getNotificationType())
            );
        }
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION =>
                Repo::submission()->getWorkflowUrlByUserRoles(
                    Repo::submission()->get($notification->assocId),
                    $notification->userId
                ),
            default => ''
        };
    }

    //
    // Private helper methods
    //
    /**
     * Get all notification types corresponding to editor decisions.
     */
    public function _getAllEditorDecisionNotificationTypes(): array
    {
        return [
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION
        ];
    }

    /**
     * Get the notification level for the type of notification being created.
     */
    public function _getNotificationTaskLevel(int $type): int
    {
        return match($type) {
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            Notification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => Notification::NOTIFICATION_LEVEL_TASK,
            default => Notification::NOTIFICATION_LEVEL_NORMAL
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorDecisionNotificationManager', '\EditorDecisionNotificationManager');
}
