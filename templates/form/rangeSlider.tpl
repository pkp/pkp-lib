{**
 * templates/form/rangeSliderInput.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * PKP handler for the jQueryUI range slider input
 *}
<script>
	$(function() {ldelim}
		$('#{$FBV_id}_container').pkpHandler('$.pkp.controllers.RangeSliderHandler',
			{ldelim}
				values: [{$FBV_value_min|string_format:"%d"}, {$FBV_value_max|string_format:"%d"}],
				min: "{$FBV_min|escape:javascript}",
				max: "{$FBV_max|escape:javascript}"
			{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id}_container" class="pkp_controllers_rangeSlider{if $FBV_layoutInfo} {$FBV_layoutInfo}{/if}">
	<label>
		{* Wrap min/max values in spans required for the live update of values,
			but construct the overall string in a way that can still be
			re-arranged by translators as needed. *}
		{capture assign="current_min_value"}
			<span class="pkp_controllers_rangeSlider_sliderValueMin">
				{$FBV_value_min}
			</span>
		{/capture}
		{capture assign="current_max_value"}
			<span class="pkp_controllers_rangeSlider_sliderValueMax">
				{$FBV_value_max}
			</span>
		{/capture}
		{capture assign="current_value"}
			{translate key="common.range" min=$current_min_value max=$current_max_value}
		{/capture}
		{translate key=$FBV_label current=$current_value}
	</label>
	<div id="{$FBV_id}_slider" class="pkp_controllers_rangeSlider_slider"></div>
	<input type="hidden" id="{$FBV_id}Min" name="{$FBV_id}Min" value="{$FBV_value_min}" class='pkp_controllers_rangeSlider_minInput' />
	<input type="hidden" id="{$FBV_id}Max" name="{$FBV_id}Max" value="{$FBV_value_max}" class='pkp_controllers_rangeSlider_maxInput' />
</div>
