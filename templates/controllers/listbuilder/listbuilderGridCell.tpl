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
		{if $column->getFlag('sourceType') == $smarty.const.LISTBUILDER_SOURCE_TYPE_SELECT}
			{**
			 * Include a hidden element containing the current key.
		 	 * Used e.g. to match the currently selected value.
			 *}
			<input type="hidden" value="{$labelKey|escape}" />
		{/if}

		{* Display the current value *}
		{include file="controllers/grid/gridCellContents.tpl"}
	</div>

	<div class="gridCellEdit">
		{if $column->getFlag('sourceType') == $smarty.const.LISTBUILDER_SOURCE_TYPE_TEXT}
			<input type="text" name="newRowId" class="textField" value="{$label|escape}" />
		{elseif $column->getFlag('sourceType') == $smarty.const.LISTBUILDER_SOURCE_TYPE_SELECT}
			<select name="newRowId" class="selectMenu">
				{* Populated by JavaScript in ListbuilderHandler.js *}
				<option value="{$labelKey|escape}">{translate key="common.loading"}</option>
			</select>
		{else}{* LISTBUILDER_SOURCE_TYPE_BOUND *}
			{* FIXME Is this type still needed? *}
		{/if}
	</div>
</span>

