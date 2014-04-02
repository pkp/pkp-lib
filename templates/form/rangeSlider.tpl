{**
 * templates/form/rangeSliderInput.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * PKP handler for the jQueryUI range slider input
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#{$FBV_id}_container').pkpHandler('$.pkp.controllers.RangeSliderHandler',
			{ldelim}
				min: "{$FBV_min|escape:javascript}",
				max: "{$FBV_max|escape:javascript}"
			{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id}_container" class="pkp_controllers_rangeSlider {if $FBV_layoutInfo}{$FBV_layoutInfo}{/if}">
	<p class="pkp_controllers_rangeSlider_sliderLabel">
		{$FBV_label_content}
		<input type="text" id="{$FBV_id}" value="{$FBV_min} - {$FBV_max}" class="pkp_controllers_rangeSlider_sliderValue{if $FBV_validation} {$FBV_validation}{/if}" />
	</p>
	<div id="{$FBV_id}_slider" class="pkp_controllers_rangeSlider_slider"></div>
	<input type="hidden" id="{$FBV_id}_min" name="{$FBV_id}_min" value="{$FBV_min}" class='pkp_controllers_rangeSlider_minInput' />
	<input type="hidden" id="{$FBV_id}_max" name="{$FBV_id}_max" value="{$FBV_max}" class='pkp_controllers_rangeSlider_maxInput' />
</div>
