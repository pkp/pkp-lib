<?php

/**
 * @file classes/notification/managerDelegate/SubmissionNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Submission notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;

class SubmissionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
            throw new \Exception('Unexpected assoc type!');
        }
        $submission = Repo::submission()->get($notification->assocId); /** @var Submission $submission */

        return match($notification->type) {
            Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => __('notification.type.submissionSubmitted', ['title' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')]),
            Notification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION => __('notification.type.submissionNewVersion'),
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => __('notification.type.editorAssignmentTask'),
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        $router = $request->getRouter();
        $dispatcher = $router->getDispatcher();

        if ($notification->assocType != Application::ASSOC_TYPE_SUBMISSION) {
            throw new \Exception('Unexpected assoc type for notification!');
        }

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
            case Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->contextId);

                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'submission', $notification->assocId);
            case Notification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->contextId);

                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'production', $notification->assocId);
        }
        throw new \Exception('Unexpected notification type!');
    }

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass(Notification $notification): string
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => 'notifyIconPageAlert',
            Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => 'notifyIconNewPage',
        };
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return match($notification->type) {
            Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => NOTIFICATION_STYLE_CLASS_INFORMATION,
            Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => '',
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\SubmissionNotificationManager', '\SubmissionNotificationManager');
}
