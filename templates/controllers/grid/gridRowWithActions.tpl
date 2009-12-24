{**
 * gridRowWithActions.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid row with Actions
 *}
{assign var=rowId value="`$row->getGridId()`-row-`$row->getId()`"}
{capture name=rowActions}
	{foreach name=actions from=$row->getActions() item=action}
		{include file="controllers/grid/gridAction.tpl" action=$action id=$rowId}
		{if $smarty.foreach.actions.last}
			&nbsp;&nbsp;&nbsp;
		{else}
			<br />
		{/if}
	{/foreach}
{/capture}
<tr id="{$rowId}">
	{foreach name=cellForEach from=$cells item=cell}
		{if $smarty.foreach.cellForEach.first}
			<td>
				{$smarty.capture.rowActions}
				{$cell}
			</td>
		{else}
			{$cell}
		{/if}
	{/foreach}
</tr>