{**
 * templates/notification/unsubscribeNotificationsResult.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Unsubscribe Notifications return page
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="notification.unsubscribeNotifications"}

<div class="page page_unsubscribe_notifications">
	{capture assign="profileNotificationUrl"}{url page="user" op="profile"}{/capture}
	{if $unsubscribeResult}
		<h1>{translate key="notification.unsubscribeNotifications.success"}</h1>
		<p>{translate key="notification.unsubscribeNotifications.successMessage" profileNotificationUrl=$profileNotificationUrl email=$userEmail}</p>
	{else}
		<h1>{translate key="notification.unsubscribeNotifications.error"}</h1>
		<p>{translate key="notification.unsubscribeNotifications.errorMessage" profileNotificationUrl=$profileNotificationUrl email=$userEmail}</p>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
