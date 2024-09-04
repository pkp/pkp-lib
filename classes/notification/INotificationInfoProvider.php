<?php

/**
 * @file classes/notification/INotificationInfoProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class INotificationInfoProvider
 *
 * @ingroup notification
 *
 * @brief Interface to retrieve notification presentation information.
 */

namespace PKP\notification;

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
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string;

    /**
     * Get the notification message. Only return translated locale
     * key strings.
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null;

    /**
     * Get the notification contents. Content is anything that's
     * more than text, like presenting link actions inside fetched
     * template files.
     */
    public function getNotificationContents(PKPRequest $request, Notification $notification): mixed;

    /**
     * Get the notification title.
     */
    public function getNotificationTitle(Notification $notification): string;

    /**
     * Get the notification style class.
     */
    public function getStyleClass(Notification $notification): string;

    /**
     * Get the notification icon class.
     */
    public function getIconClass(Notification $notification): string;

    /**
     * Whether any notification with the passed notification type
     * is visible to all users or not.
     */
    public function isVisibleToAllUsers(int $notificationType, int $assocType, int $assocId): bool;
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\INotificationInfoProvider', '\INotificationInfoProvider');
}
