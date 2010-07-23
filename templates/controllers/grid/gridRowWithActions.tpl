{**
 * gridRowWithActions.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}
{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
<tr id="{$rowId}">
	{foreach name=columnLoop from=$columns key=columnId item=column}
		{if $smarty.foreach.columnLoop.first}
			<td class="first_column">
				<div class="row_container">
					<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">{$cells[0]}</div>
						<div class="row_actions">
							{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
								<a class="settings sprite"><span class="hidetext">{translate key="grid.settings"}</span></a>
							{/if}
							{if $row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT)}
								{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT) item=action}
									{include file="linkAction/linkAction.tpl" action=$action id=$rowId hoverTitle=true}
								{/foreach}
							{/if}
						</div>
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
							<div class="row_controls">
								{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) item=action}
									{include file="linkAction/linkAction.tpl" action=$action id=$rowId}
								{/foreach}
							</div>
						{/if}
				</div>
			</td>
		{else}
			<td>{$cells[$smarty.foreach.columnLoop.index]}</td>
		{/if}
	{/foreach}
</tr>
