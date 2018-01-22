{**
 * templates/notification/index.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of notifications.
 *
 *}
{include file="common/header.tpl" pageTitle="notification.notifications"}

<div class="pkp_page_content pkp_page_notifications">

{if $isUserLoggedIn}
	<p>{translate key="notification.notificationsDescription" unreadCount=$unread readCount=$read settingsUrl=$url}</p>
{else}
	<p>{translate key="notification.notificationsPublicDescription" emailUrl=$emailUrl}</p>
{/if}
<a href="{url op="getNotificationFeedUrl" path="rss"}" class="icon"><img src="{$baseUrl|escape}/lib/pkp/templates/images/rss10_logo.svg" alt="RSS 1.0"/></a>
<a href="{url op="getNotificationFeedUrl" path="rss2"}" class="icon"><img src="{$baseUrl}/lib/pkp/templates/images/rss20_logo.svg" alt="RSS 2.0"/></a>
<a href="{url op="getNotificationFeedUrl" path="atom"}" class="icon"><img src="{$baseUrl}/lib/pkp/templates/images/atom.svg" alt="Atom 1.0"/></a>

<br/>

{if $isUserLoggedIn}
	<div id="normalNotifications">
		{url|assign:notificationsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.NormalNotificationsGridHandler" op="fetchGrid" escape=false}
		{load_url_in_div id="normalNotificationsGridContainer" url=$notificationsGridUrl}
	</div>
{/if}

</div>

{include file="common/footer.tpl"}
