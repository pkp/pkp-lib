{**
 * templates/form/autocompleteInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * an autocomplete input
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#{$FBV_id}_container').pkpHandler('$.pkp.controllers.AutocompleteHandler',
			{ldelim}
				source: "{$FBV_autocompleteUrl|escape:javascript}"
			{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id}_container">
	{$FBV_textInput}
	<input type="hidden" name="{$FBV_id}" id="{$FBV_id}" {if $FBV_validation}class="{$FBV_validation}"{/if} />
</div>