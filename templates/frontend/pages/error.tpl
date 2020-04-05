{**
 * templates/frontend/pages/error.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Generic error page.
 * Displays a simple error message and (optionally) a return link.
 *}
{include file="frontend/components/header.tpl"}

<div class="page page_error">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey=$pageTitle}
	<h1>
		{translate key=$pageTitle}
	</h1>
	<div class="description">
		{translate key=$errorMsg params=$errorParams}
	</div>
	{if $backLink}
		<div class="cmp_back_link">
			<a href="{$backLink}">{translate key=$backLinkLabel}</a>
		</div>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
