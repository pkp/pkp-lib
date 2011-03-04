{**
 * gridRowWithActions.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}

{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
<tr id="{$rowId}" class="element{$row->getId()} gridRow">
	{foreach name=columnLoop from=$columns key=columnId item=column}
		{if $smarty.foreach.columnLoop.first}
			<td class="first_column">
				<div class="row_container">
					<div class="row_file {if $column->hasFlag('multiline')}multiline{/if}">{$cells[0]}</div>
					<div class="row_actions pkp_linkActions">
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
							<a class="settings sprite"><span class="hidetext">{translate key="grid.settings"}</span></a>
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
					{if $row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT)}
						<div class="row_controls pkp_linkActions">
							{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_DEFAULT) item=action}
								{if is_a($action, 'LegacyLinkAction')}
									{include file="linkAction/legacyLinkAction.tpl" action=$action id=$rowId}
								{else}
									{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
								{/if}
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

