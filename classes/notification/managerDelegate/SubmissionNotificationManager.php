<?php

/**
 * @file classes/notification/managerDelegate/SubmissionNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Submission notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\notification\NotificationManagerDelegate;

use PKP\notification\PKPNotification;

class SubmissionNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
        $submission = Repo::submission()->get($notification->getAssocId()); /** @var Submission $submission */

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
                return __('notification.type.submissionSubmitted', ['title' => $submission->getLocalizedTitle()]);
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
                return __('notification.type.submissionNewVersion');
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                return __('notification.type.editorAssignmentTask');
            default:
                assert(false);
        }
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $router = $request->getRouter();
        $dispatcher = $router->getDispatcher();

        assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->getContextId());

                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'submission', $notification->getAssocId());
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION:
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->getContextId());

                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'production', $notification->getAssocId());
            default:
                assert(false);
        }
    }

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass($notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                return 'notifyIconPageAlert';
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
                return 'notifyIconNewPage';
            default:
                assert(false);
        }
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                return NOTIFICATION_STYLE_CLASS_INFORMATION;
            case PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
                return '';
            default:
                assert(false);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\SubmissionNotificationManager', '\SubmissionNotificationManager');
}
