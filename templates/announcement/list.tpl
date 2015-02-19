{**
 * lib/pkp/templates/announcement/list.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of announcements without site header or footer.
 *
 *}
<table class="announcements">
	<tr>
		<td colspan="2" class="headseparator">&nbsp;</td>
	</tr>

{counter start=1 skip=1 assign="count"}
{iterate from=announcements item=announcement}
	{if !$numAnnouncementsHomepage || $count <= $numAnnouncementsHomepage}
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
				<td class="more"><a href="{url page="announcement" op="view" path=$announcement->getId()}">{translate key="announcement.viewLink"}</a></td>
			{/if}
		</tr>
	{/if}
	<tr>
		<td colspan="2" class="{if $announcements->eof()}end{/if}separator">&nbsp;</td>
	</tr>
	{counter}
{/iterate}
{if $announcements->wasEmpty()}
	<tr>
		<td colspan="2" class="nodata">{translate key="announcement.noneExist"}</td>
	</tr>
	<tr>
		<td colspan="2" class="endseparator">&nbsp;</td>
	</tr>
{/if}
</table>
