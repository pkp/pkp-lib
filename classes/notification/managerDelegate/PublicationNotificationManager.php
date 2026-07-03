<?php

/**
 * @file classes/notification/managerDelegate/PublicationNotificationManager.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Publication notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\publication\PKPPublication;

abstract class PublicationNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        if ($notification->assocType != Application::ASSOC_TYPE_PUBLICATION) {
            throw new \Exception('Unexpected assoc type!');
        }
        $publication = Repo::publication()->get($notification->assocId); /** @var PKPPublication $publication */

        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED => __('notification.type.publicationPublished', ['title' => $publication->getLocalizedTitle(null, 'html')]),
        };
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        if ($notification->assocType != Application::ASSOC_TYPE_PUBLICATION) {
            throw new \Exception('Unexpected assoc type for notification!');
        }

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED:
                $publication = Repo::publication()->get($notification->assocId); /** @var PKPPublication $publication */
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($notification->contextId);

                return $this->getPublicationUrl($request, $context, $publication);
        }
        throw new \Exception('Unexpected notification type!');
    }

    abstract public function getPublicationUrl(PKPRequest $request, Context $context, PKPPublication $publication): string;

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass(Notification $notification): string
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED => 'notifyIconNewPage',
        };
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return match ($notification->type) {
            Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED => NOTIFICATION_STYLE_CLASS_INFORMATION,
        };
    }
}
