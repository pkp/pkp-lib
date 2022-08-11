<?php

/**
 * @file classes/notification/NotificationManagerDelegate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationManagerDelegate
 * @ingroup notification
 *
 * @brief Abstract class to support notification manager delegates
 * that provide default implementation to the interface that defines
 * notification presentation data. It also introduce a method to be
 * extended by subclasses to update notification objects.
 */

namespace PKP\notification;

use APP\notification\Notification;

abstract class NotificationManagerDelegate extends PKPNotificationOperationManager
{
    /** @var int NOTIFICATION_TYPE_... */
    private $_notificationType;

    /**
     * Constructor.
     *
     * @param int $notificationType NOTIFICATION_TYPE_...
     */
    public function __construct($notificationType)
    {
        $this->_notificationType = $notificationType;
    }

    /**
     * Get the current notification type this manager is handling.
     *
     * @return int NOTIFICATION_TYPE_...
     */
    public function getNotificationType()
    {
        return $this->_notificationType;
    }

    /**
     * Define operations to update notifications.
     *
     * @param PKPRequest $request Request object
     * @param array $userIds List of user IDs to notify
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId ID corresponding to $assocType
     *
     * @return bool True iff success
     */
    public function updateNotification($request, $userIds, $assocType, $assocId)
    {
        return false;
    }

    /**
     * Check if this manager delegate can handle the
     * creation of the passed notification type.
     *
     * @copydoc PKPNotificationOperationManager::createNotification()
     *
     * @param null|mixed $userId
     * @param null|mixed $notificationType
     * @param null|mixed $contextId
     * @param null|mixed $assocType
     * @param null|mixed $assocId
     * @param null|mixed $params
     */
    public function createNotification($request, $userId = null, $notificationType = null, $contextId = null, $assocType = null, $assocId = null, $level = Notification::NOTIFICATION_LEVEL_NORMAL, $params = null)
    {
        assert($notificationType == $this->getNotificationType() || $this->multipleTypesUpdate());
        return parent::createNotification($request, $userId, $notificationType, $contextId, $assocType, $assocId, $level, $params);
    }

    /**
     * Flag a notification manager that handles multiple notification
     * types inside the update method within the same call. Only set
     * this to true if you're sure the notification manager provides
     * all information for all notification types you're handling (via
     * the getNotification... methods).
     *
     * @return bool
     */
    protected function multipleTypesUpdate()
    {
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationManagerDelegate', '\NotificationManagerDelegate');
}
