{**
 * templates/controllers/grid/gridRow.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
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

		{* @todo indent columns should be killed at their source *}
		{if $column->hasFlag('indent')}
			{php}continue;{/php}
		{/if}

		<td
		{if $column->hasFlag('firstColumn')} class="first_column{if !$row->hasActions() && !$row->getNoActionMessage()} no_actions{/if}"{/if}
		{if ($row->hasActions() || $row->getNoActionMessage()) && $column->hasFlag('firstColumn')}
				>
				<div class="row_container">
					<div class="row_actions">
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) || $row->getNoActionMessage()}
							<a class="sprite show_extras" title="{translate key="grid.settings"}"><span class="hidetext">{translate key="grid.settings"}</span></a>
						{/if}
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT)}
							{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT) item=action}
								{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
							{/foreach}
						{/if}
					</div>
					<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">
						{$cells[$smarty.foreach.columnLoop.index]}
						{if is_a($row, 'GridCategoryRow') && $column->hasFlag('showTotalItemsNumber')}
							<span class="category_items_number">({$grid->getCategoryItemsCount($categoryRow->getData(), $request)})</span>
						{/if}
					</div>
				</div>
			</td>
		{else}
			{if $column->hasFlag('alignment')}
				{assign var=alignment value=$column->getFlag('alignment')}
			{else}
				{assign var=alignment value=$smarty.const.COLUMN_ALIGNMENT_LEFT}
			{/if}
			style="text-align: {$alignment}">
				{$cells[$smarty.foreach.columnLoop.index]}
			</td>
		{/if}
	{/foreach}
</tr>
{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) || $row->getNoActionMessage()}
	<tr id="{$rowId|escape}-control-row" class="row_controls{if is_a($row, 'GridCategoryRow')} category_controls{/if}">
		<td colspan="{$grid->getColumnsCount('indent')}">
			{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
				{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) item=action}
					{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
				{/foreach}
			{else}
				{$row->getNoActionMessage()}
			{/if}
		</td>
	</tr>
{/if}
