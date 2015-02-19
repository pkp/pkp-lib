{**
 * templates/controllers/grid/gridHeader.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid header HTML markup
 *}
<div class="grid_header">
	{$gridFilterForm}
	{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) || $grid->getTitle()}
		<div class="grid_header_bar">
			{if $grid->getTitle()}<h3>{$grid->getTitle()|translate}</h3>{/if}
			{if $grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE)}
				{include file="controllers/grid/gridActionsAbove.tpl" actions=$grid->getActions($smarty.const.GRID_ACTION_POSITION_ABOVE) gridId=$staticId}
			{/if}
			<div class="pkp_helpers_clear"></div>
		</div>
	{/if}
	{if $grid->getInstructions()}
		<p class="pkp_grid_description">{translate key=$grid->getInstructions()}</p>
	{/if}
</div>
