<?php

/**
 * @file classes/mail/traits/Notification.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup mail_traits
 *
 * @brief trait to support NOTIFICATION email template variables
 */

namespace PKP\mail\traits;

use APP\core\Application;
use APP\notification\Notification as SystemNotification;
use APP\notification\NotificationManager;

trait Notification
{
    protected static string $notificationContents = 'notificationContents';
    protected static string $notificationUrl = 'notificationUrl';
    protected static string $unsubscribeLink = 'unsubscribeLink';

    abstract public function addData(array $data);

    protected static function addNotificationVariablesDescription(array $variables): array
    {
        $variables[static::$notificationContents] = __('emailTemplate.variable.notificationContents');
        $variables[static::$notificationUrl] = __('emailTemplate.variable.notificationUrl');
        $variables[static::$unsubscribeLink] = __('emailTemplate.variable.unsubscribeLink');
        return $variables;
    }

    protected function setupNotificationVariables(SystemNotification $notification)
    {
        $notificationManager = new NotificationManager(); /** @var NotificationManager $notificationManager */
        $request = Application::get()->getRequest();
        $unsubscribeUrl = $notificationManager->getUnsubscribeNotificationUrl($request, $notification);
        $this->addData([
            'notificationContents' => $notificationManager->getNotificationContents($request, $notification),
            'notificationUrl' => $notificationManager->getNotificationUrl($request, $notification),
            'unsubscribeLink' => '<br /><a href=\'' . $unsubscribeUrl . '\'>' . __('notification.unsubscribeNotifications') . '</a>'
        ]);
    }
}
