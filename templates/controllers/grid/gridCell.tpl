{**
 * templates/controllers/grid/gridCell.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid cell (with or without actions)
 *}
{if $id}
	{assign var=cellId value="cell-"|concat:$id}
{else}
	{assign var=cellId value=""}
{/if}
<span {if $cellId}id="{$cellId|escape}" {/if}class="pkp_linkActions gridCellContainer">
	<div class="gridCellDisplay">
		{if count($actions) gt 0}
			{assign var=action value=$actions[0]}
			{if is_a($action, 'LegacyLinkAction')}
				{include file="linkAction/legacyLinkAction.tpl" id=$cellId|concat:"-action-":$action->getId() action=$action objectId=$cellId}
			{else}
				{include file="linkAction/linkAction.tpl" action=$action contextId=$cellId}
			{/if}
		{elseif $column->hasFlag('html')}
			{* Limited HTML is allowed *}
			{$label|strip_unsafe_html}
		{else}
			{$label|escape}
		{/if}
	</div>
	{if $column->hasFlag('editable')}
		{* The column can be edited i.e. is displayed in an input field when clicked *}
		<div class="gridCellEdit">
			<input type="text" name="{$column->getId()|escape}" class="textField" value="{$label|escape}" />
		</div>
	{/if}
</span>

