{**
 * templates/controllers/grid/gridRow.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid row
 *}
{if $row->getId()}
	{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
{else}
	{assign var=rowId value=""}
{/if}
<tr {if $rowId}id="{$rowId|escape}" {/if}class="{if $rowId}element{$row->getId()|escape} {/if}gridRow">
	{foreach from=$cells item=cell}
		<td>{$cell}</td>
	{/foreach}
</tr>

