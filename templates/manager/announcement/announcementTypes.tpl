{**
 * lib/pkp/templates/manager/announcement/announcementTypes.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of announcement types in management.
 *
 *}
{include file="common/header.tpl" pageTitle="manager.announcementTypes" pageId="manager.announcementTypes"}

<ul class="menu">
	<li><a href="{url op="announcements"}">{translate key="manager.announcements"}</a></li>
	<li class="current"><a href="{url op="announcementTypes"}">{translate key="manager.announcementTypes"}</a></li>
</ul>

{capture assign=announcementTypeGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.AnnouncementTypeGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="announcementTypeGridContainer" url=$announcementTypeGridUrl}

{include file="common/footer.tpl"}
