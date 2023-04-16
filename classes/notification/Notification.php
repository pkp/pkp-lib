<?php

/**
 * @file classes/notification/Notification.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Notification
 *
 * @ingroup notification
 *
 * @see NotificationDAO
 *
 * @brief OPS subclass for Notifications (defines OPS-specific types).
 */

namespace APP\notification;

use PKP\notification\PKPNotification;

class Notification extends PKPNotification
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\notification\Notification', '\Notification');
}
