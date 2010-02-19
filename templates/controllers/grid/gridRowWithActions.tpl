{**
 * gridRowWithActions.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}
{assign var=rowId value="component-`$row->getGridId()`-row-`$row->getId()`"}
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
	{foreach name=cellForEach from=$cells item=cell}
		{if $smarty.foreach.cellForEach.first}
			<td class="first_column">
    			<div class="row_container">
					<div class="row_file">
						{$cell}
						{**if notes <a href="#" class="notes sprite"><span class="hidetext">Notes</span></a> **}
					</div>
					<div class="row_actions">
						<a class="settings sprite"><span class="hidetext">{translate key="grid.settings"}</span></a>
					</div>
				</div>
				{$smarty.capture.rowActions}
			</td>
		{else}
			{$cell}
		{/if}
	{/foreach}
</tr>