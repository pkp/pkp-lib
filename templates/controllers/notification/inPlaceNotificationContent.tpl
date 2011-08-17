{**
 * controllers/notification/inPlaceNotificationContent.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a single notification for in place notifications data.
 *}

<div id="pkp_notification_{$notificationId|escape}">
	<div class="pkp_notification_title">
		{translate key="notification.notification"}
	</div>
	<div class="pkp_notification_content">
		{if $notificationContents}
			{$notificationContents|nl2br}
		{/if}
	</div>
</div>
