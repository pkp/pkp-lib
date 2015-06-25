{**
 * templates/controllers/page/tasks.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User-specific tasks panel.
 *}

{**
* Unread notifications count is set here on header load, but
* can also be updated dynamically via the javascript event
* called updateUnreadNotificationsCount.
*}
<a href="#" id="notificationsToggle">
	{translate key="common.tasks"}
	(<span id="unreadNotificationCount">{$unreadNotificationCount}</span>)
</a>
<div id="notificationsPopover" style="display: none;">
	{url|assign:notificationsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.NotificationsGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="notificationsGrid" url=$notificationsGridUrl}
</div>
