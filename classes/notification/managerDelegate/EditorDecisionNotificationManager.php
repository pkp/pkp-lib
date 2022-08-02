<?php

/**
 * @file classes/notification/managerDelegate/EditorDecisionNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Editor decision notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\facades\Repo;
use APP\notification\Notification;
use PKP\db\DAORegistry;

use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;

class EditorDecisionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW:
                return __('notification.type.editorDecisionInternalReview');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
                return __('notification.type.editorDecisionAccept');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
                return __('notification.type.editorDecisionExternalReview');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
                return __('notification.type.editorDecisionPendingRevisions');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
                return __('notification.type.editorDecisionResubmit');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND:
                return __('notification.type.editorDecisionNewRound');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
                return __('notification.type.editorDecisionDecline');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE:
                return __('notification.type.editorDecisionRevertDecline');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
                return __('notification.type.editorDecisionSendToProduction');
            default:
                return null;
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
     * @copydoc PKPNotificationOperationManager::getNotificationTitle()
     */
    public function getNotificationTitle($notification)
    {
        return __('notification.type.editorDecisionTitle');
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification($request, $userIds, $assocType, $assocId)
    {
        $context = $request->getContext();

        // Remove any existing editor decision notifications.
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationFactory = $notificationDao->getByAssoc(
            ASSOC_TYPE_SUBMISSION,
            $assocId,
            null,
            null,
            $context->getId()
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
                $context->getId(),
                ASSOC_TYPE_SUBMISSION,
                $assocId,
                $this->_getNotificationTaskLevel($this->getNotificationType()),
                null,
                true // suppressEmail
            );
        }
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
                $submission = Repo::submission()->get($notification->getAssocId());
                return Repo::submission()->getWorkflowUrlByUserRoles($submission, $notification->getUserId());
            default:
                return '';
        }
    }

    //
    // Private helper methods
    //
    /**
     * Get all notification types corresponding to editor decisions.
     *
     * @return array
     */
    public function _getAllEditorDecisionNotificationTypes()
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
     *
     * @param int $type
     *
     * @return int
     */
    public function _getNotificationTaskLevel($type)
    {
        switch ($type) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
                return Notification::NOTIFICATION_LEVEL_TASK;
            default:
                return Notification::NOTIFICATION_LEVEL_NORMAL;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorDecisionNotificationManager', '\EditorDecisionNotificationManager');
}
