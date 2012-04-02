{**
 * templates/controllers/grid/gridOrderFinishControls.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Controls (button and link) to finish the ordering action in grids.
 *}
<div id="{$gridId}-order-finish-controls" class="grid_controls pkp_helpers_display_none">
{fbvElement type="link" class="cancelFormButton pkp_helpers_align_left" id=$gridId|concat:"-cancel" label="grid.action.cancelOrdering"}
{fbvElement type="link" class="saveButton pkp_helpers_align_right" id=$gridId|concat:"-saveButton" label="common.done"}
</div>
