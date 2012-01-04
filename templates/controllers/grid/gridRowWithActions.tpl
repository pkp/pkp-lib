{**
 * gridRowWithActions.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}
{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
{capture name=rowActions}
	{if $row->getActions()}
		<div class="row_controls">
			{foreach name=actions from=$row->getActions() item=action}
				{include file="controllers/grid/gridAction.tpl" action=$action id=$rowId}
			{/foreach}
		</div>
	{/if}
{/capture}
<tr id="{$rowId}">
	{foreach name=columnLoop from=$columns item=column}
		{if $smarty.foreach.columnLoop.first}
			<td class="first_column">
				<div class="row_container">
					<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">
						{$cells[$smarty.foreach.columnLoop.index]}
					</div>
					<div class="row_actions">
						<a class="settings sprite"><span class="hidetext">{translate key="grid.settings"}</span></a>
					</div>
					{$smarty.capture.rowActions}
				</div>
			</td>
		{else}
			{$cells[$smarty.foreach.columnLoop.index]}
		{/if}
	{/foreach}
</tr>
