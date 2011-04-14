{**
 * templates/controllers/listbuilderGridRow.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a listbuilder grid row
 *}
{if $row->getId()}
	{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
{else}
	{assign var=rowId value=""}
{/if}
<tr {if $rowId}id="{$rowId|escape}" {/if}class="{if $rowId}element{$row->getId()|escape} {/if}gridRow">
	{foreach from=$cells item=cell name=listbuilderCells}
		<td class="gridCell{if $smarty.foreach.listbuilderCells.last} lastGridCell{/if}">{$cell}</td>
	{/foreach}
	{if $row->getId()}
		<input type="hidden" name="rowId" value="{$row->getId()|escape}" />
	{/if}
	{if !$row->getId() || $row->getIsModified()}
		<input type="hidden" disabled="disabled" class="isModified" value="1" />
	{/if}
</tr>
