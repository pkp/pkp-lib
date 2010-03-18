{**
 * grid.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * grid HTML markup and construction
 *
 * FIXME: Re-introduce "partial width" when needed without
 *  configuring an actual pixel width in the controller.
 *}

{assign var=gridId value="component-`$grid->getId()`"}
{assign var=gridTableId value="`$gridId`-table"}
<div id="{$gridId}" class="grid">
	<div class="wrapper">
		<span class="options">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) item=action}
				{if $action->getType() eq $smarty.const.GRID_ACTION_TYPE_NOTHING}
					{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId}
				{else}
					{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId actOnId="`$gridTableId` > tbody"}
				{/if}
			{/foreach}
		</span>
		<h3>{$grid->getTitle()|translate}</h3>
		<table id="{$gridTableId}">
		    <colgroup>
		    	{"<col />"|str_repeat:$numColumns}
		    </colgroup>
		    <thead>
		    	<tr>
		    		{** build the column headers **}
		    		{foreach from=$columns item=column}
		        		<th scope="col">{$column->getTitle()|translate}</th>
					{/foreach}
		        </tr>
		    </thead>
			{foreach from=$gridBodyParts item=bodyPart}
				{$bodyPart}
			{/foreach}
		    <tbody>
				{**
					We need the last (=empty) line even if we have rows
					so that we can restore it if the user deletes all rows.
				**}
				<tr class="empty"{if $rows} style="display: none;"{/if}>
					<td colspan="{$numColumns}">{translate key="grid.noItems"}</td>
				</tr>
		    </tbody>
		</table>
		<div class="actions">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) item=action}
				{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId actOnId="`$gridTableId` > tbody"}
			{/foreach}
		</div>
	</div>
</div>
