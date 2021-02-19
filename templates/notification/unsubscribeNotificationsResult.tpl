{**
 * templates/notification/unsubscribeNotificationsResult.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Unsubscribe Notifications return page
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="notification.unsubscribeNotifications"}

<div class="page page_unsubscribe_notifications">
	{capture assign="profileNotificationUrl"}<a href="{url page="user" op="profile"}">{translate key="notification.notifications"}</a>{/capture}
	{if $unsubscribeResult}
		{translate key="notification.unsubscribeNotifications.successMessage" profileNotificationUrl=$profileNotificationUrl contextName=$contextName username=$username}
	{else}
		{translate key="notification.unsubscribeNotifications.errorMessage" profileNotificationUrl=$profileNotificationUrl contextName=$contextName username=$username}
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
