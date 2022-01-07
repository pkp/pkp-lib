<?php

/**
 * @file classes/notification/NotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 * @ingroup notification
 *
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */

namespace APP\notification;

use APP\core\Application;
use APP\facades\Repo;

use APP\notification\managerDelegate\ApproveSubmissionNotificationManager;
use PKP\core\PKPApplication;
use PKP\notification\PKPNotificationManager;

class NotificationManager extends PKPNotificationManager
{
    /** @var array Cache each user's most privileged role for each submission */
    public $privilegedRoles;

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $router = $request->getRouter();
        $dispatcher = $router->getDispatcher();

        switch ($notification->getType()) {
            // OPS: links leading to a new submission have to be redirected to production stage
            case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->getContextId());
                return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'workflow', 'production', $notification->getAssocId());
            default:
                return parent::getNotificationUrl($request, $notification);
        }
    }

    /**
     * Helper function to get an preprint title from a notification's associated object
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function _getPreprintTitle($notification)
    {
        assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION);
        assert(is_numeric($notification->getAssocId()));
        $preprint = Repo::submission()->get($notification->getAssocId());
        if (!$preprint) {
            return null;
        }
        return $preprint->getLocalizedTitle();
    }

    /**
     * Return a CSS class containing the icon of this notification type
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function getIconClass($notification)
    {
        switch ($notification->getType()) {
            case NOTIFICATION_TYPE_NEW_ANNOUNCEMENT:
                return 'notifyIconNewAnnouncement';
            default: return parent::getIconClass($notification);
        }
    }

    /**
     * @copydoc PKPNotificationManager::getMgrDelegate()
     */
    protected function getMgrDelegate($notificationType, $assocType, $assocId)
    {
        switch ($notificationType) {
            case NOTIFICATION_TYPE_APPROVE_SUBMISSION:
                assert($assocType == ASSOC_TYPE_SUBMISSION && is_numeric($assocId));
                return new ApproveSubmissionNotificationManager($notificationType);
        }
        // Otherwise, fall back on parent class
        return parent::getMgrDelegate($notificationType, $assocType, $assocId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\notification\NotificationManager', '\NotificationManager');
}
