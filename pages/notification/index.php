<?php

/**
 * @defgroup pages_notification Notification Pages
 */

/**
 * @file pages/notification/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_notification
 * @brief Handle requests for viewing notifications.
 *
 */


switch ($op) {
	case 'fetchNotification':
		define('HANDLER_CLASS', 'NotificationHandler');
		import('lib.pkp.pages.notification.NotificationHandler');
		break;
}


