{**
 * controllers/extrasOnDemand.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Basic markup for extras on demand widget.
 *}
<script>
	// Initialise JS handler.
	$(function() {ldelim}
		$(('{$parentContainer}' + ' #{$id}').trim())
			.last()
			.pkpHandler('$.pkp.controllers.ExtrasOnDemandHandler');
	{rdelim});
</script>
<div id="{$id}" class="pkp_controllers_extrasOnDemand">
	<a href="#" class="toggleExtras">
		<span class="toggleExtras-inactive">{translate key=$moreDetailsText}</span>
		<span class="toggleExtras-active">{translate key=$lessDetailsText}</span>
		<span class="fa fa-plus"></span>
	</a>
	<div class="extrasContainer container">
		{$extraContent}
	</div>
</div>
