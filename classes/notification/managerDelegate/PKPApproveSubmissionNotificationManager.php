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
use PKP\db\DAORegistry;
use PKP\notification\NotificationDAO;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;

class PKPApproveSubmissionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $dispatcher = Application::get()->getDispatcher();
        $context = $request->getContext();
        return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $notification->getAssocId());
    }

    /**
     * @copydoc PKPNotificationOperationManager::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * @copydoc PKPNotificationOperationManager::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers($notificationType, $assocType, $assocId)
    {
        return true;
    }

    /**
     * @copydoc NotificationManagerDelegate::updateNotification()
     */
    public function updateNotification($request, $userIds, $assocType, $assocId)
    {
        $submissionId = $assocId;
        $submission = Repo::submission()->get($submissionId);

        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */

        $notificationTypes = [
            PKPNotification::NOTIFICATION_TYPE_APPROVE_SUBMISSION => false,
            PKPNotification::NOTIFICATION_TYPE_FORMAT_NEEDS_APPROVED_SUBMISSION => false,
            PKPNotification::NOTIFICATION_TYPE_VISIT_CATALOG => true,
        ];

        $isPublished = (bool) $submission->getDatePublished();

        foreach ($notificationTypes as $type => $forPublicationState) {
            $notificationFactory = $notificationDao->getByAssoc(
                Application::ASSOC_TYPE_SUBMISSION,
                $submissionId,
                null,
                $type,
                $submission->getData('contextId')
            );
            $notification = $notificationFactory->next();

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
                $notificationDao->deleteObject($notification);
            }
        }
    }

    /**
     * @copydoc NotificationManagerDelegate.php
     */
    protected function multipleTypesUpdate()
    {
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\PKPApproveSubmissionNotificationManager', '\PKPApproveSubmissionNotificationManager');
}
