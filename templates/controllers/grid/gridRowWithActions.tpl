{**
 * templates/controllers/grid/gridRowWithActions.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}
{if $row->getId()}
	{assign var=rowIdPrefix value="component-"|concat:$row->getGridId()}
	{if $categoryRow}
		{assign var=rowIdPrefix value=$rowIdPrefix|concat:"-category-":$categoryRow->getId()}
	{/if}
	{assign var=rowId value=$rowIdPrefix|concat:"-row-":$row->getId()}
{else}
	{assign var=rowId value=""}
{/if}
<tr id="{$rowId|escape}" class="element{$row->getId()|escape} {if $row->getIsOrderable()}orderable{/if} gridRow">
	{foreach name=columnLoop from=$columns key=columnId item=column}
		{if $column->hasFlag('hasRowActionsToggle')}
			<td class="first_column">
				<div class="row_container">
					<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">{$cells[$smarty.foreach.columnLoop.index]}</div>
					<div class="row_actions">
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
							<a class="sprite settings"><span class="hidetext">{translate key="grid.settings"}</span></a>
						{/if}
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT)}
							{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT) item=action}
								{if is_a($action, 'LegacyLinkAction')}
									{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
										{assign var=actionActOnId value=$action->getActOn()}
									{else}
										{assign var=actionActOnId value=$gridActOnId}
									{/if}
									{include file="linkAction/legacyLinkAction.tpl" action=$action id=$rowId hoverTitle=true}
								{else}
									{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
								{/if}
							{/foreach}
						{/if}
					</div>
				</div>
			</td>
		{else}
			{if $column->hasFlag('alignment')}
				{assign var=alignment value=$column->getFlag('alignment')}
			{else}
				{assign var=alignment value=$smarty.const.COLUMN_ALIGNMENT_CENTER}
			{/if}
			<td style="text-align: {$alignment}" {if $column->hasFlag('indent')}class="no_border indent_row"{/if}>{$cells[$smarty.foreach.columnLoop.index]}</td>
		{/if}
	{/foreach}
</tr>
<tr id="{$rowId|escape}-control-row" class="row_controls">
	{if $grid->getColumnsByFlag('indent')}
		<td class="no_border indent_row"></td>
	{/if}
	<td colspan="{$grid->getColumnsCount('indent')}">
		{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
			{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) item=action}
				{if is_a($action, 'LegacyLinkAction')}
					{include file="linkAction/legacyLinkAction.tpl" action=$action id=$rowId}
				{else}
					{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
				{/if}
			{/foreach}
		{/if}
	</td>
</tr>

