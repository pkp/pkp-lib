{**
 * templates/controllers/grid/gridRow.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * A grid row.
 *}
{if !is_null($row->getId())}
	{assign var=rowIdPrefix value="component-"|concat:$row->getGridId()}
	{if $categoryId}
		{assign var=rowIdPrefix value=$rowIdPrefix|concat:"-category-":$categoryId|escape}
	{/if}
	{assign var=rowId value=$rowIdPrefix|concat:"-row-":$row->getId()}
{else}
	{assign var=rowId value=""}
{/if}
<tr {if $rowId}id="{$rowId|escape}" {/if}class="{if $rowId}element{$row->getId()|escape} {/if}gridRow{if is_a($row, 'GridCategoryRow')} category{if !$row->hasFlag('gridRowStyle')} default_category_style{/if}{/if}">
	{foreach name=columnLoop from=$columns key=columnId item=column}
		{if $column->hasFlag('indent')}
			{if !is_a($row, 'GridCategoryRow')}
				<td class="no_border indent_row"></td>
				{assign var=columnSpan value=0}
			{else}
				{assign var=columnSpan value=2}
			{/if}
		{else}
			<td {if $columnSpan && $smarty.foreach.columnLoop.iteration == 2}colspan="{$columnSpan}"{/if}
			{if $column->hasFlag('firstColumn')} class="first_column{if !$row->hasActions()} no_actions{/if}"{/if}
			{if $row->hasActions() && $column->hasFlag('firstColumn')}
					>
					<div class="row_container">
						<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">{$cells[$smarty.foreach.columnLoop.index]}</div>
						<div class="row_actions">
							{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
								<a class="sprite settings" title="{translate key="grid.settings"}"><span class="hidetext">{translate key="grid.settings"}</span></a>
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
				style="text-align: {$alignment}">
					{$cells[$smarty.foreach.columnLoop.index]}
				</td>
			{/if}
		{/if}
	{/foreach}
</tr>
{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
	<tr id="{$rowId|escape}-control-row" class="row_controls{if is_a($row, 'GridCategoryRow')} category_controls{/if}">
		{if $grid->getColumnsByFlag('indent')}
			<td class="{if !is_a($row, 'GridCategoryRow')}no_border {/if}indent_row"></td>
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
{/if}

