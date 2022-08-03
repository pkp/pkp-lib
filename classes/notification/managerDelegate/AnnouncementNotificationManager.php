<?php
/**
 * @file classes/notification/managerDelegate/AnnouncementNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementNotificationManager
 * @ingroup managerDelegate
 *
 * @brief New announcement notification manager.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\notification\Notification;
use PKP\announcement\Announcement;
use PKP\core\PKPApplication;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Repo;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\user\User;

class AnnouncementNotificationManager extends NotificationManagerDelegate
{
    /** @var Announcement The announcement to send a notification about */
    public $_announcement;

    /**
     * Initializes the class.
     *
     * @param Announcement $announcement The announcement to send
     */
    public function initialize(Announcement $announcement): void
    {
        $this->_announcement = $announcement;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification): string
    {
        return __('emails.announcement.subject');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationContents($request, $notification): EmailTemplate
    {
        return Repo::emailTemplate()->getByKey($notification->getContextId(), 'ANNOUNCEMENT');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $request->getContext()->getData('path'),
            'announcement',
            'view',
            $this->_announcement->getId()
        );
    }

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass($notification): string
    {
        return 'notifyIconInfo';
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass($notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * Sends a notification to the given user.
     *
     * @param User $user The user who will be notified
     *
     * @return PKPNotification|null The notification instance or null if no notification created
     */
    public function notify(User $user): ?PKPNotification
    {
        return parent::createNotification(
            Application::get()->getRequest(),
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT,
            $this->_announcement->getAssocId(),
            null,
            null,
            Notification::NOTIFICATION_LEVEL_NORMAL,
            ['contents' => $this->_announcement->getLocalizedTitle()]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\AnnouncementNotificationManager', '\AnnouncementNotificationManager');
}
