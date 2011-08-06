{**
 * templates/controllers/grid/grid.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid HTML markup and construction
 *}

{assign var=gridId value="component-"|concat:$grid->getId()}
{assign var=gridTableId value=$gridId|concat:"-table"}
{if $grid|is_a:CategoryGridHandler}
	{assign var=gridActOnId value=$gridTableId}
{else}
	{assign var=gridActOnId value=$gridTableId|concat:">tbody:first"}
{/if}

<script type="text/javascript">
	$(function() {ldelim}
		$('#{$gridId|escape:javascript}').pkpHandler(
			'$.pkp.controllers.grid.GridHandler',
			{ldelim}
				gridId: '{$grid->getId()|escape:javascript}',
				fetchRowUrl: '{url|escape:javascript op='fetchRow' params=$gridRequestArgs escape=false}',
				fetchGridUrl: '{url|escape:javascript op='fetchGrid' params=$gridRequestArgs escape=false}',
				bodySelector: '#{$gridActOnId|escape:javascript}',
			{rdelim}
		);
	{rdelim});
</script>

<div id="{$gridId|escape}" class="pkp_controllers_grid">
	{if !$grid->getIsSubcomponent()}<div class="wrapper">{/if}
		<div class="header">
			{$gridFilterForm}
			{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
				{include file="controllers/grid/gridActionsAbove.tpl" actions=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) gridId=$gridId}
			{/if}
			{if !$grid->getIsSubcomponent()}<span class="h3 no_border float_left">{$grid->getTitle()|translate}</span>{/if}
		</div>
		{translate key=$grid->getInstructions()}
		<table id="{$gridTableId|escape}">
			<colgroup>
				{foreach from=$columns item=column}<col {if $column->hasFlag('myUserGroup')}class="my_role"{/if}/>{/foreach}
			</colgroup>
			<thead>
				{** build the column headers **}
				<tr>
					{foreach name=columns from=$columns item=column}
						{if $column->hasFlag('alignment')}
							{assign var=alignment value=$column->getFlag('alignment')}
						{else}
							{assign var=alignment value=$smarty.const.COLUMN_ALIGNMENT_CENTER}
						{/if}
						<th scope="col" style="text-align: {$alignment};{if $column->hasFlag('width')} width: {$column->getFlag('width')}%"{/if}">
							{$column->getLocalizedTitle()}
							{* TODO: Remove this stuff.  Actions should not ever appear in the TH of a grid. *}
							{if $smarty.foreach.columns.last && $grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL)}
								<span class="options pkp_linkActions">
									{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL) item=action}
										{if is_a($action, 'LegacyLinkAction')}
											{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
												{assign var=actionActOnId value=$action->getActOn()}
											{else}
												{assign var=actionActOnId value=$gridActOnId}
											{/if}
											{include file="linkAction/legacyLinkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId hoverTitle=true}
										{else}
											{include file="linkAction/linkAction.tpl" action=$action contextId=$gridId}
										{/if}
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
					<td colspan="{$columns|@count}">{translate key="grid.noItems"}</td>
				</tr>
			</tbody>
		</table>
		{if $grid->getIsSubcomponent()}
			</div>
		{/if}

		{include file="controllers/grid/gridActionsBelow.tpl" actions=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) gridId=$gridId}

	{if !$grid->getIsSubcomponent()}</div>{/if}
</div>
