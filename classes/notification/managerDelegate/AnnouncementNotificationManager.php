<?php
/**
 * @file classes/notification/managerDelegate/AnnouncementNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief New announcement notification manager.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use PKP\announcement\Announcement;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\facades\Repo;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\user\User;

class AnnouncementNotificationManager extends NotificationManagerDelegate
{
    /** The announcement to send a notification about */
    public Announcement $_announcement;

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
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        return __('emails.announcement.subject');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationContents(PKPRequest $request, Notification $notification): mixed
    {
        return Repo::emailTemplate()->getByKey($notification->contextId, 'ANNOUNCEMENT');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $request->getContext()->getData('urlPath'),
            'announcement',
            'view',
            $this->_announcement->getAttribute('announcementId')
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
     * @return Notification|null The notification instance or null if no notification created
     */
    public function notify(User $user): ?Notification
    {
        return parent::createNotification(
            Application::get()->getRequest(),
            $user->getId(),
            Notification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT,
            $this->_announcement->getAttribute('assocId'),
            null,
            null,
            Notification::NOTIFICATION_LEVEL_NORMAL,
            ['contents' => $this->_announcement->getLocalizedData('title')]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\AnnouncementNotificationManager', '\AnnouncementNotificationManager');
}
