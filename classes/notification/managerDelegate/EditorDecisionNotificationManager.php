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
use APP\notification\Notification;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;

class EditorDecisionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, PKPNotification $notification): ?string
    {
        return match ($notification->getType()) {
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW => __('notification.type.editorDecisionInternalReview'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT => __('notification.type.editorDecisionAccept'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW => __('notification.type.editorDecisionExternalReview'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS => __('notification.type.editorDecisionPendingRevisions'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => __('notification.type.editorDecisionResubmit'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND => __('notification.type.editorDecisionNewRound'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE => __('notification.type.editorDecisionDecline'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE => __('notification.type.editorDecisionRevertDecline'),
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION => __('notification.type.editorDecisionSendToProduction'),
            default => null
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(PKPNotification $notification): string
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
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var \PKP\notification\NotificationDAO $notificationDao */
        $notificationFactory = $notificationDao->getByAssoc(
            Application::ASSOC_TYPE_SUBMISSION,
            $assocId,
            null,
            null,
            $request->getContext()->getId()
        );

        // Delete old notifications.
        $editorDecisionNotificationTypes = $this->_getAllEditorDecisionNotificationTypes();
        while ($notification = $notificationFactory->next()) {
            // If a list of user IDs was specified, make sure we're respecting it.
            if ($userIds !== null && !in_array($notification->getUserId(), $userIds)) {
                continue;
            }

            // Check that the notification type is in the specified list.
            if (!in_array($notification->getType(), $editorDecisionNotificationTypes)) {
                continue;
            }

            $notificationDao->deleteObject($notification);
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
    public function getNotificationUrl(PKPRequest $request, PKPNotification $notification): ?string
    {
        return match ($notification->getType()) {
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION =>
                Repo::submission()->getWorkflowUrlByUserRoles(
                    Repo::submission()->get($notification->getAssocId()),
                    $notification->getUserId()
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
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION
        ];
    }

    /**
     * Get the notification level for the type of notification being created.
     */
    public function _getNotificationTaskLevel(int $type): int
    {
        return match($type) {
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => Notification::NOTIFICATION_LEVEL_TASK,
            default => Notification::NOTIFICATION_LEVEL_NORMAL
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorDecisionNotificationManager', '\EditorDecisionNotificationManager');
}
