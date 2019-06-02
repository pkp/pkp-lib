{**
 * templates/form/autocompleteInput.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * an autocomplete input
 *}
<script>
	$(function() {ldelim}
		$('#{$FBV_id}_container').pkpHandler('$.pkp.controllers.AutocompleteHandler',
			{ldelim}
				{if $FBV_disableSync}disableSync: true,{/if}
				sourceUrl: {$FBV_autocompleteUrl|json_encode},
			{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id}_container" {if $FBV_layoutInfo}{$FBV_layoutInfo}{/if}>
	{$FBV_textInput}
	<div class="hidden">
		<input type="hidden" name="{$FBV_id}" id="{$FBV_id}" {if $FBV_autocompleteValue}value="{$FBV_autocompleteValue}"{/if} {if $FBV_validation}class="{$FBV_validation}"{/if} />
	</div>
</div>
