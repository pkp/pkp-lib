{**
 * grid.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
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
	{if !$grid->getIsSubcomponent()}<div class="wrapper">{/if}
		{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
			<span class="options">
				{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) item=action}
					{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
						{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$action->getActOn()}
					{else}
						{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$gridTableId}
					{/if}
				{/foreach}
			</span>
		{/if}
		{if !$grid->getIsSubcomponent()}<h3>{$grid->getTitle()|translate}</h3>{/if}
		<table id="{$gridTableId}">
		    <colgroup>
		    	{"<col />"|str_repeat:$numColumns}
		    </colgroup>
		    <thead>
	    		{** build the column headers **}
		    	<tr>
		    		{foreach name=columns from=$columns item=column}
		        		<th scope="col">
		        			{$column->getLocalizedTitle()}
							{if $smarty.foreach.columns.last && $grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL)}
								<span class="options">
									{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL) item=action}
										{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
											{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$action->getActOn() hoverTitle=true}
										{else}
											{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$gridTableId hoverTitle=true}
										{/if}
									{/foreach}
								</span>
							{/if}
						</th>
					{/foreach}
		        </tr>
		    </thead>
		    <tbody>
			{foreach from=$gridBodyParts item=bodyPart}
				{$bodyPart}
			{/foreach}
			</tbody>
		    <tbody class="empty"{if count($gridBodyParts) > 0} style="display: none;"{/if}>
				{**
					We need the last (=empty) line even if we have rows
					so that we can restore it if the user deletes all rows.
				**}
				<tr>
					<td colspan="{$numColumns}">{translate key="grid.noItems"}</td>
				</tr>
		    </tbody>
		</table>
		<div class="actions">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) item=action}
				{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$gridTableId"}
			{/foreach}
		</div>
	{if !$grid->getIsSubcomponent()}</div>{/if}
</div>
