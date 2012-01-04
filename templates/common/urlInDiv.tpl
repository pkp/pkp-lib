{**
 * urlInDiv.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generate JS and HTML to include a URL in a DIV, AJAX-style.
 *
 *}

<div id="{$inDivDivId}"{if $inDivClass} class="{$inDivClass}"{/if}>{$inDivLoadMessage}</div>
<script type='text/javascript'>
	$(function() {ldelim}
		$.getJSON("{$inDivUrl|escape:"javascript"}", function(jsonData) {ldelim}
			if (jsonData.status === true) {ldelim}
				$("#{$inDivDivId}").hide().html(jsonData.content).fadeIn(400);
			{rdelim} else {ldelim}
				// Alert that loading failed
				alert(jsonData.content);
			{rdelim}
		{rdelim});
	{rdelim});
</script>

