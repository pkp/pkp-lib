<?php

/**
 * @defgroup pages_notification
 */

/**
 * @file pages/notification/index.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_notification
 * @brief Handle requests for viewing notifications.
 *
 */

// $Id: index.php,v 1.4 2009/12/10 00:57:04 asmecher Exp $

switch ($op) {
	case 'index':
	case 'delete':
	case 'settings':
	case 'saveSettings':
	case 'getNotificationFeedUrl':
	case 'notificationFeed':
	case 'subscribeMailList':
	case 'saveSubscribeMailList':
	case 'mailListSubscribed':
	case 'confirmMailListSubscription':
	case 'unsubscribeMailList':
		define('HANDLER_CLASS', 'NotificationHandler');
		import('pages.notification.NotificationHandler');
		break;
}

?>
