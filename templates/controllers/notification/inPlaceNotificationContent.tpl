{**
 * controllers/notification/inPlaceNotificationContent.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a single notification for in place notifications data.
 *}

<div id="pkp_notification_{$notificationId|escape}" class="notification_block {$notificationStyleClass}">
	<h4>{$notificationTitle}:</h4>
	<span class="description">
		{if $notificationContents}
			<p>{$notificationContents}</p>
		{/if}
	</span>
</div>
