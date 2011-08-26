{**
 * controllers/notification/inPlaceNotificationContent.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a single notification for in place notifications data.
 *}

<div id="pkp_notification_{$notificationId|escape}" class="notification_block {$notificationStyleClass}">
	{if $notificationContents.title}
		<h4>{$notificationContents.title}:</h4>
	{else}
		<h4>{translate key="notification.notification"}</h4>
	{/if}
	<span class="description">
		{if $notificationContents.description}
			<p>{$notificationContents.description}</p>
		{/if}
	</span>
</div>