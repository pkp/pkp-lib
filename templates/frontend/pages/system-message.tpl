{**
 * templates/frontend/pages/message.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Generic message page.
 * Displays a simple message and (optionally) a return link.
 *}
{assign var="pageTitleTranslated" value=$title}
{include file="frontend/components/header.tpl"}

<div class="page page_{if $type}{$type}{else}message{/if}">
	{include file="frontend/components/breadcrumbs.tpl" currentTitle=$title}
	<h1>
		{$title}
	</h1>
	<div class="description">
    {$message|strip_unsafe_html}
	</div>
	{if $backLink}
		<div class="cmp_back_link">
			<a href="{$backLink}">{$backLinkLabel}</a>
		</div>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
