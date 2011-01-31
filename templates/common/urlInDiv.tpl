{**
 * urlInDiv.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generate JS and HTML to include a URL in a DIV, AJAX-style.
 *
 *}

<div id="{$inDivDivId}"{if $inDivClass} class="{$inDivClass}"{/if}>{$inDivLoadMessage}</div>
<script type='text/javascript'>
	{literal}
	$(function() {
		$.getJSON("{/literal}{$inDivUrl|escape:"javascript"}{literal}", function(jsonData) {
			if (jsonData.status === true) {
				$("#{/literal}{$inDivDivId}{literal}").html(jsonData.content);
			} else {
				// Alert that loading failed
				alert(jsonData.content);
			}
		});
	});
	{/literal}
</script>

