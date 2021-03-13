<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OPSNotification
 * @ingroup notification
 * @see NotificationDAO
 * @brief OPS subclass for Notifications (defines OPS-specific types).
 */

/** Notification associative types. */
// OPS-specific trivial notifications

import('lib.pkp.classes.notification.PKPNotification');
import('lib.pkp.classes.notification.NotificationDAO');

class Notification extends PKPNotification { }

