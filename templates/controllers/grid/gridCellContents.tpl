{**
 * templates/controllers/grid/gridCellContents.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid cell's contents
 *}
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
