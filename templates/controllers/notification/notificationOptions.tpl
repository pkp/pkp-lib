{**
 * controllers/notification/notificationOptions.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Notification options.
 *}

fetchNotificationUrl: '{url|escape:javascript router=$smarty.const.ROUTE_PAGE page='notification' op='fetchNotification' params=$requestOptions escape=false}',
hasSystemNotifications: '{$hasSystemNotifications}'

