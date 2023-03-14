<?php

/**
 * @file classes/notification/INotificationInfoProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class INotificationInfoProvider
 * @ingroup notification
 * @brief Interface to retrieve notification presentation information.
 */

namespace PKP\notification;

use APP\notification\Notification;
use PKP\core\PKPRequest;

define('NOTIFICATION_STYLE_CLASS_WARNING', 'notifyWarning');
define('NOTIFICATION_STYLE_CLASS_INFORMATION', 'notifyInfo');
define('NOTIFICATION_STYLE_CLASS_SUCCESS', 'notifySuccess');
define('NOTIFICATION_STYLE_CLASS_ERROR', 'notifyError');
define('NOTIFICATION_STYLE_CLASS_FORM_ERROR', 'notifyFormError');
define('NOTIFICATION_STYLE_CLASS_FORBIDDEN', 'notifyForbidden');
define('NOTIFICATION_STYLE_CLASS_HELP', 'notifyHelp');

interface INotificationInfoProvider
{
    /**
     * Get a URL for the notification.
     *
     * @param PKPRequest $request
     * @param Notification $notification
     *
     * @return string
     */
    public function getNotificationUrl($request, $notification);

    /**
     * Get the notification message. Only return translated locale
     * key strings.
     *
     * @param PKPRequest $request
     * @param Notification $notification
     *
     * @return string|array
     */
    public function getNotificationMessage($request, $notification);

    /**
     * Get the notification contents. Content is anything that's
     * more than text, like presenting link actions inside fetched
     * template files.
     *
     * @param PKPRequest $request
     * @param Notification $notification
     *
     * @return string|array
     */
    public function getNotificationContents($request, $notification);

    /**
     * Get the notification title.
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function getNotificationTitle($notification);

    /**
     * Get the notification style class.
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function getStyleClass($notification);

    /**
     * Get the notification icon class.
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function getIconClass($notification);

    /**
     * Whether any notification with the passed notification type
     * is visible to all users or not.
     *
     * @param int $notificationType
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId
     *
     * @return bool
     */
    public function isVisibleToAllUsers($notificationType, $assocType, $assocId);
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\INotificationInfoProvider', '\INotificationInfoProvider');
}
