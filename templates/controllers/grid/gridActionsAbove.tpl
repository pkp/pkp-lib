{**
 * templates/controllers/grid/gridActionsAbove.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Actions markup for upper grid actions
 *}

<ul class="actions">
	{foreach from=$actions item=action}
		<li>
			{include file="linkAction/linkAction.tpl" action=$action contextId=$gridId}
		</li>
	{/foreach}
</ul>
