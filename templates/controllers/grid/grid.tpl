{**
 * grid.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * grid HTML markup and construction
 *
 * FIXME: Re-introduce "partial width" when needed without
 *  configuring an actual pixel width in the controller.
 *}

{assign var=gridId value="component-"|concat:$grid->getId()}
{assign var=gridTableId value=$gridId|concat:"-table"}
{if $grid|is_a:CategoryGridHandler}
	{assign var=gridActOnId value=$gridTableId}
{else}
	{assign var=gridActOnId value=$gridTableId|concat:">tbody:first"}
{/if}
<div id="{$gridId}" class="grid">
	{if !$grid->getIsSubcomponent()}<div class="wrapper">{/if}
		{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
			<span class="options">
				{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) item=action}
					{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
						{assign var=actionActOnId value=$action->getActOn()}
					{else}
						{assign var=actionActOnId value=$gridActOnId}
					{/if}
					{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId}
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
											{assign var=actionActOnId value=$action->getActOn()}
										{else}
											{assign var=actionActOnId value=$gridActOnId}
										{/if}
										{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId hoverTitle=true}
									{/foreach}
								</span>
							{/if}
						</th>
					{/foreach}
		        </tr>
		    </thead>
			{if $grid->getIsSubcomponent()}
				{* Create two separate tables so that the body part
				   can be scrolled independently from the header in a
				   cross-browser compatible way using only CSS. *}
				</table>
				<div class="scrollable">
				<table>
			{/if}
			{foreach from=$gridBodyParts item=bodyPart}
				{$bodyPart}
			{foreachelse}
				<tbody></tbody>
			{/foreach}
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
		{if $grid->getIsSubcomponent()}
			</div>
		{/if}
		<div class="actions">
			{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) item=action}
				{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
					{assign var=actionActOnId value=$action->getActOn()}
				{else}
					{assign var=actionActOnId value=$gridActOnId}
				{/if}
				{include file="linkAction/linkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId}
			{/foreach}
		</div>
	{if !$grid->getIsSubcomponent()}</div>{/if}
</div>

