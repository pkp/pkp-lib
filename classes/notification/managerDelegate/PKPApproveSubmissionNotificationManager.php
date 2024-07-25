<?php

/**
 * @file classes/notification/managerDelegate/PKPApproveSubmissionNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApproveSubmissionNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Approve submission notification type manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;

class PKPApproveSubmissionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        $dispatcher = Application::get()->getDispatcher();
        $context = $request->getContext();
        return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->assocId);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
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
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $assocId): void
    {
        $submissionId = $assocId;
        $submission = Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();

        $notificationTypes = [
            Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION => false,
            Notification::NOTIFICATION_TYPE_FORMAT_NEEDS_APPROVED_SUBMISSION => false,
            Notification::NOTIFICATION_TYPE_VISIT_CATALOG => true,
        ];

        $isPublished = (bool) $publication->getData('datePublished');

        foreach ($notificationTypes as $type => $forPublicationState) {
            $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                ->withType($type)
                ->withContextId($submission->getData('contextId'))
                ->first();

            if (!$notification && $isPublished == $forPublicationState) {
                // Create notification.
                $this->createNotification(
                    $request,
                    null,
                    $type,
                    $submission->getData('contextId'),
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submissionId
                );
            } elseif ($notification && $isPublished != $forPublicationState) {
                // Delete existing notification.
                $notification->delete();
            }
        }
    }

    /**
     * @copydoc NotificationManagerDelegate.php
     */
    protected function multipleTypesUpdate(): bool
    {
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\PKPApproveSubmissionNotificationManager', '\PKPApproveSubmissionNotificationManager');
}
