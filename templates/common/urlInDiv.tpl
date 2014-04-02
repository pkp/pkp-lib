{**
 * templates/common/urlInDiv.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the contents of a URL in a DIV, AJAX-style.
 *
 *}

<script type="text/javascript">
	// Initialise JS handler.
	<!--
	$(function() {ldelim}
		$('#{$inDivDivId|escape:"js"}').pkpHandler(
			'$.pkp.controllers.UrlInDivHandler',
			{ldelim}
				sourceUrl: '{$inDivUrl|escape:"javascript"}'
			{rdelim}
		);
	{rdelim});
	// -->
</script>

<div id="{$inDivDivId|escape}"{if $inDivClass} class="{$inDivClass|escape}"{/if}>{$inDivLoadMessage}</div>
