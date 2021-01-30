{**
 * templates/controllers/grid/feature/gridOrderFinishControls.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Controls (button and link) to finish the ordering action in grids.
 *}
<div class="grid_controls order_finish_controls">
	<a href="#" id="{$gridId|concat}-saveButton" class="saveButton">
		{translate key="common.done"}
	</a>
	<a href="#" id="{$gridId|concat}-cancel" class="cancelFormButton">
		{translate key="grid.action.cancelOrdering"}
	</a>
</div>
