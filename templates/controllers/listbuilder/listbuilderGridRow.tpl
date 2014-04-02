{**
 * templates/controllers/listbuilderGridRow.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a listbuilder grid row
 *}
{if $row->getId()}
	{assign var=rowId value="component-"|concat:$row->getGridId():"-row-":$row->getId()}
{else}
	{assign var=rowId value="component-"|concat:$row->getGridId():"-row-tempId-"|uniqid}
{/if}
<tr {if $rowId}id="{$rowId|escape}" {/if}class="{if $rowId}element{$row->getId()|escape} {/if}gridRow">
	{foreach from=$cells item=cell name=listbuilderCells}
		{if $smarty.foreach.listbuilderCells.first}
			<td class="first_column">
				{if $row->getId()}
					<input type="hidden" name="rowId" value="{$row->getId()|escape}" />
				{/if}
				{if !$row->getId() || $row->getIsModified()}
					<input type="hidden" disabled="disabled" class="isModified" value="1" />
				{else}
					<input type="hidden" disabled="disabled" class="isModified" value="0" />
				{/if}
				<div class="row_container">
					<div class="gridCell row_file">{$cell}</div>
					<div class="row_actions">
						{if $row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT)}
							{foreach from=$row->getActions($smarty.const.GRID_ACTION_POSITION_ROW_LEFT) item=action}
								{include file="linkAction/linkAction.tpl" action=$action contextId=$rowId}
							{/foreach}
						{/if}
					</div>
				</div>
			</td>
		{else}
			<td class="gridCell">{$cell}</td>
		{/if}
	{/foreach}
</tr>
