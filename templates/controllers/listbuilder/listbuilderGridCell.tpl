{**
 * templates/controllers/grid/listbuilderGridCell.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular listbuilder grid cell (with or without actions)
 *}
{if $id}
	{assign var=cellId value="cell-"|concat:$id}
{else}
	{assign var=cellId value=""}
{/if}
<span {if $cellId}id="{$cellId|escape}" {/if}class="pkp_linkActions gridCellContainer">
	<div class="gridCellDisplay">
		{include file="controllers/grid/gridCellContents.tpl"}
	</div>

	<div class="gridCellEdit">
		<input type="text" name="{$column->getId()|escape}" class="textField" value="{$label|escape}" />
	</div>
</span>

