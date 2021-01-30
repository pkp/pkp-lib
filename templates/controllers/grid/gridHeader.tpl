{**
 * templates/controllers/grid/gridHeader.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Grid header HTML markup
 *}
<div class="header">

	{if $grid->getTitle()}
		<h4>
			{$grid->getTitle()|translate}
		</h4>
	{/if}

	{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
		{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
			{include file="controllers/grid/gridActionsAbove.tpl" actions=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) gridId=$staticId}
		{/if}
	{/if}
</div>

{$gridFilterForm}
