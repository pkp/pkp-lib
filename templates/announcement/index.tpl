{**
 * lib/pkp/templates/announcement/index.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of announcements.
 *
 *}
{strip}
{assign var="pageTitle" value="announcement.announcements"}
{assign var="pageId" value="announcement.announcements"}
{include file="common/header.tpl"}
{/strip}

<div id="announcementList">
<table class="announcements">
{if $announcementsIntroduction != null}
	<tr class="intro">
		<td colspan="2" class="intro">{$announcementsIntroduction|nl2br}</td>
	</tr>
{/if}
{iterate from=announcements item=announcement}
	<tr class="title">
	{if $announcement->getTypeId()}
		<td class="title"><h4>{$announcement->getAnnouncementTypeName()|escape}: {$announcement->getLocalizedTitle()|escape}</h4></td>
	{else}
		<td class="title"><h4>{$announcement->getLocalizedTitle()|escape}</h4></td>
	{/if}
		<td class="more">&nbsp;</td>
	</tr>
	<tr class="description">
		<td class="description">{$announcement->getLocalizedDescriptionShort()|nl2br}</td>
		<td class="more">&nbsp;</td>
	</tr>
	<tr class="details">
		<td class="posted">{translate key="announcement.posted"}: {$announcement->getDatePosted()}</td>
		{if $announcement->getLocalizedDescription() != null}
			<td class="more"><a href="{url op="view" path=$announcement->getId()}">{translate key="announcement.viewLink"}</a></td>
		{/if}
	</tr>
	<tr>
		<td colspan="2" class="{if $announcements->eof()}end{/if}separator">&nbsp;</td>
	</tr>
{/iterate}
{if $announcements->wasEmpty()}
	<tr>
		<td colspan="2" class="nodata">{translate key="announcement.noneExist"}</td>
	</tr>
	<tr>
		<td colspan="2" class="endseparator">&nbsp;</td>
	</tr>
{else}
	<tr>
		<td align="left">{page_info iterator=$announcements}</td>
		<td align="right">{page_links anchor="announcements" name="announcements" iterator=$announcements}</td>
	</tr>
{/if}
</table>
</div>

{include file="common/footer.tpl"}
