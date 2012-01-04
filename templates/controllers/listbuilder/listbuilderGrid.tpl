{**
 * listbuilder.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays a ListBuilder object
 *}

<div class="listbuilderGrid">
<table id="listGrid-{$listbuilderId}{if $itemId}-{$itemId}{/if}">
    <tbody>
		{foreach from=$rows item=row}
			{$row}
		{/foreach}
		{**
			We need the last (=empty) line even if we have rows
			so that we can restore it if the user deletes all rows.
		**}
		<tr class="empty"{if $rows} style="display: none;"{/if}>
			<td colspan="{$numColumns}">{translate key="grid.noItems"}</td>
		</tr>
    </tbody>
</table>
</div>
