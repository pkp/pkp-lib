{**
 * templates/controllers/grid/gridActionsBelow.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid actions in bottom position
 *}

<div class="actions pkp_linkActions grid_link_actions_below">
	{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) item=action}
		{if is_a($action, 'LegacyLinkAction')}
			{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
				{assign var=actionActOnId value=$action->getActOn()}
			{else}
				{assign var=actionActOnId value=$gridActOnId}
			{/if}
			{include file="linkAction/legacyLinkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId}
		{else}
			{include file="linkAction/linkAction.tpl" action=$action contextId=$gridId}
		{/if}
	{/foreach}
	<div class="pkp_helpers_clear"></div>
</div>
