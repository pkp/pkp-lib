<?php

/**
 * @file classes/notification/NotificationManagerDelegate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationManagerDelegate
 *
 * @ingroup notification
 *
 * @brief Abstract class to support notification manager delegates
 * that provide default implementation to the interface that defines
 * notification presentation data. It also introduce a method to be
 * extended by subclasses to update notification objects.
 */

namespace PKP\notification;

use APP\core\Application;
use PKP\core\PKPRequest;

abstract class NotificationManagerDelegate extends PKPNotificationOperationManager
{
    /** @var int NOTIFICATION_TYPE_... */
    private $_notificationType;

    /**
     * Constructor.
     */
    public function __construct(int $notificationType)
    {
        $this->_notificationType = $notificationType;
    }

    /**
     * Get the current notification type this manager is handling.
     */
    public function getNotificationType(): int
    {
        return $this->_notificationType;
    }

    /**
     * Define operations to update notifications.
     */
    public function updateNotification(PKPRequest $request, ?array $userIds, int $assocType, int $assocId): void
    {
    }

    /**
     * Check if this manager delegate can handle the
     * creation of the passed notification type.
     *
     * @copydoc PKPNotificationOperationManager::createNotification()
     */
    public function createNotification(PKPRequest $request, ?int $userId = null, ?int $notificationType = null, ?int $contextId = Application::SITE_CONTEXT_ID, ?int $assocType = null, ?int $assocId = null, int $level = Notification::NOTIFICATION_LEVEL_NORMAL, ?array $params = null): ?Notification
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
     */
    protected function multipleTypesUpdate(): bool
    {
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationManagerDelegate', '\NotificationManagerDelegate');
}
