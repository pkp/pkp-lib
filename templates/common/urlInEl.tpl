{**
 * templates/common/urlInEl.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Include the contents of a URL in a DIV, AJAX-style.
 *
 * @param ?bool $inVueEl Whether or not this div is used in HTML code that is
 *	processed by Vue.js's template compiler.
 *
 *}

{if $inVueEl}
<component is="script">
{else}
<script>
{/if}
	// Initialize JS handler.
	$(function() {ldelim}
		$('#{$inElElId|escape:javascript}').pkpHandler(
			'$.pkp.controllers.UrlInDivHandler',
			{ldelim}
				sourceUrl: {$inElUrl|json_encode},
				refreshOn: {$refreshOn|json_encode}
			{rdelim}
		);
	{rdelim});
{if $inVueEl}
</component>
{else}
</script>
{/if}

<{$inEl} id="{$inElElId|escape}"{if $inElClass} class="{$inElClass|escape}"{/if}>{$inElPlaceholder}</{$inEl}>
