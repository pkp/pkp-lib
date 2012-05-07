{**
 * templates/controllers/grid/gridRow.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid row
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
<tr {if $rowId}id="{$rowId|escape}" {/if}class="{if $rowId}element{$row->getId()|escape} {/if}gridRow">
	{foreach name=columnLoop from=$columns key=columnId item=column}
		{if $column->hasFlag('alignment')}
			{assign var=alignment value=$column->getFlag('alignment')}
		{else}
			{assign var=alignment value=$smarty.const.COLUMN_ALIGNMENT_CENTER}
		{/if}
		<td style="text-align: {$alignment}" {if $column->hasFlag('indent')}class="no_border indent_row"{/if}>{$cells[$smarty.foreach.columnLoop.index]}</td>
	{/foreach}
</tr>

