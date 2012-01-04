{**
 * grid.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * grid HTML markup and construction
 *
 * FIXME: Re-introduce "partial width" when needed without
 *  configuring an actual pixel width in the controller.
 *}

{assign var=gridId value="component-"|concat:$grid->getId()}
{assign var=gridTableId value=$gridId|concat:"-table"}
<div id="{$gridId}" class="grid">
	<div class="wrapper">
		<span class="options">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) item=action}
				{if $action->getMode() eq $smarty.const.GRID_ACTION_MODE_AJAX}
					{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId}
				{else}
					{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId actOnId=$gridTableId}
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
		        		<th scope="col">{$column->getLocalizedTitle()}</th>
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
				<tr class="empty"{if count($gridBodyParts) > 0} style="display: none;"{/if}>
					<td colspan="{$numColumns}">{translate key="grid.noItems"}</td>
				</tr>
		    </tbody>
		</table>
		<div class="actions">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) item=action}
				{include file="controllers/grid/gridAction.tpl" action=$action id=$gridId actOnId=$gridTableId"}
			{/foreach}
		</div>
	</div>
</div>
