{**
 * index.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of notifications.
 *
 *}
{strip}
{assign var="pageTitle" value="notification.notifications"}
{include file="common/header.tpl"}
{/strip}

<table width="100%">
	<tr>
		<td>{if $isUserLoggedIn}
				<p>{translate key="notification.notificationsDescription" unreadCount=$unread readCount=$read settingsUrl=$url}</p>
			{else}
				<p>{translate key="notification.notificationsPublicDescription" emailUrl=$emailUrl}</p>
			{/if}
		</td>
		<td><ul class="plain">
			<li><a href="{url op="getNotificationFeedUrl" path="rss"}" class="icon"><img src="{$baseUrl}/lib/pkp/templates/images/rss10_logo.gif" alt="RSS 1.0"/></a></li>
			<li><a href="{url op="getNotificationFeedUrl" path="rss2"}" class="icon"><img src="{$baseUrl}/lib/pkp/templates/images/rss20_logo.gif" alt="RSS 2.0"/></a></li>
			<li><a href="{url op="getNotificationFeedUrl" path="atom"}" class="icon"><img src="{$baseUrl}/lib/pkp/templates/images/atom10_logo.gif" alt="Atom 1.0"/></a></li>
		</ul></td>
	</tr>
</table>

<br/>

<div id="notifications">
{iterate from=notifications item=notification}
	<table width="100%" class="notifications">
		<tr>
			<td width="25"><img src="{$notification->getIconLocation()}" alt="&#187;"/></td>
			<td class="notificationContent" colspan="2" width="80%">
				{$notification->getDateCreated()}
			</td>
			{if $notification->getLocation() != null}
				<td class="notificationFunction" style="min-width:60px"><a href="{$notification->getLocation()}">{translate key="notification.location"}</a></td>
			{else}
				<td class="notificationFunction"></td>
			{/if}
			{if $isUserLoggedIn}
				<td class="notificationFunction"><a href="{url op="delete" path=$notification->getId()}">{translate key="common.delete"}</a></td>
			{/if}
		</tr>
		<tr>
			<td width="25">&nbsp;</td>
			<td class="notificationContent">
				{if $notification->getIsUnread()}
					{if $notification->getIsLocalized()}<p style="font-weight: bold">{translate key=$notification->getContents() param=$notification->getParam()}</p>
					{else}<p style="font-weight: bold">{$notification->getContents()}{/if}
				{else}
					{if $notification->getIsLocalized()}<p>{translate key=$notification->getContents() param=$notification->getParam()}</p>
					{else}<p>{$notification->getContents()}</p>{/if}
				{/if}
			</td>
		</tr>
	</table>
	<div class="separator" style="margin-left:25px"></div>
{/iterate}
{if $notifications->wasEmpty()}
	<table class="notifications">
		<tr>
			<td colspan="2" class="nodata"><h5>{translate key="notification.noneExist"}</h5></td>
		</tr>
		<tr>
			<td colspan="2" class="endseparator">&nbsp;</td>
		</tr>
	</table>
{else}
	<table class="notifications">
		<tr>
			<td align="left">{page_info iterator=$notifications}</td>
			<td align="right">{page_links anchor="notifications" name="notifications" iterator=$notifications}</td>
		</tr>
	</table>
{/if}

</div>

{include file="common/footer.tpl"}
