{**
 * templates/common/urlInDiv.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Include the contents of a URL in a DIV, AJAX-style.
 *
 *}

<script>
	// Initialise JS handler.
	$(function() {ldelim}
		$('#{$inElElId|escape:"js"}').pkpHandler(
			'$.pkp.controllers.UrlInDivHandler',
			{ldelim}
				sourceUrl: {$inElUrl|json_encode},
				refreshOn: {$refreshOn|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<{$inEl} id="{$inElElId|escape}"{if $inElClass} class="{$inElClass|escape}"{/if}>{$inElPlaceholder}</{$inEl}>
