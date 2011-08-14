{**
 * controllers/notification/inPlaceNotificationContent.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display in place notifications data.
 *}
{foreach from=$notifications item=notification}
	<div id="pkp_notification_{$notification->getId()|escape}">
		{assign var=isLocalized value=$notification->getIsLocalized()}
		<div class="pkp_notification_title">
			{if $notification->getTitle()}
				{if $isLocalized}
					{translate key=$notification->getTitle()}
				{else}
					{$notification->getTitle()|escape}
				{/if}
			{else}
				{translate key="notification.notification"}
			{/if}
		</div>
		<div class="pkp_notification_content">
			{if $notification->getContents()}
				{if $isLocalized}
					{translate key=$notification->getContents()}
				{else}
					{$notification->getContents()|escape}
				{/if}
			{/if}
		</div>
	</div>
{/foreach}
<div class="separator"></div>