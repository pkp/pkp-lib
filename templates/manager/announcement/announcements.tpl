{**
 * lib/pkp/templates/announcement/announcements.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of announcements in management.
 *
 *}
{include file="common/header.tpl" pageTitle="manager.announcements" pageId="manager.announcements"}

<ul class="menu">
	<li class="current"><a href="{url op="announcements"}">{translate key="manager.announcements"}</a></li>
	<li><a href="{url op="announcementTypes"}">{translate key="manager.announcementTypes"}</a></li>
</ul>

{capture assign=announcementGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.ManageAnnouncementGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="announcementGridContainer" url=$announcementGridUrl}

{include file="common/footer.tpl"}
