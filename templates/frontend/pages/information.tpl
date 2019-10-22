{**
 * templates/frontend/pages/information.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Information page.
 *
 *}
{if !$contentOnly}
	{include file="frontend/components/header.tpl" pageTitle=$pageTitle}
{/if}

<div class="page page_information">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey=$pageTitle}
	<h1 class="pageCurrentTitle">
		{translate key=$pageTitle}
	</h1>
	{include file="frontend/components/editLink.tpl" page="management" op="settings" path="website" anchor="information" sectionTitleKey="manager.website.information"}

	<div class="description">
		{$content}
	</div>
</div>

{if !$contentOnly}
	{include file="frontend/components/footer.tpl"}
{/if}
