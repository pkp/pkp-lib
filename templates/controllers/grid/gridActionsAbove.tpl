{**
 * templates/controllers/grid/gridActionsAbove.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Actions markup for upper grid actions
 *}

<span class="options">
	{foreach from=$actions item=action}
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
</span>
