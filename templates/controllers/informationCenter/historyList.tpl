{**
 * templates/controllers/informationCenter/historyList.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission or file history in information center.
 *}

<div id="{$historyListId}">
<table width="100%" class="pkp_listing">
	<tr><td colspan="5">&nbsp;</td></tr>
	<tr valign="top" class="heading">
		<td>{translate key="common.date"}</td>
		<td>{translate key="common.user"}</td>
		<td>{translate key="common.event"}</td>
	</tr>

	{iterate from=eventLogEntries item=logEntry}
		{include file="controllers/informationCenter/logEntry.tpl"}
	{/iterate}
	{if $eventLogEntries->wasEmpty()}
		<tr valign="top">
			<td colspan="5" class="nodata pkp_helpers_text_center">{translate key="informationCenter.history.noItems"}</td>
		</tr>
	{/if}
</table>
</div>
