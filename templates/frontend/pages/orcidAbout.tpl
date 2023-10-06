{**
 * templates/frontend/pages/orcidAbout.tpl
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Copyright (c) 2018-2019 University Library Heidelberg
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Page template to display from the OrcidHandler to show information/overview about ORCID functionality for users.
 *}
{include file="frontend/components/header.tpl"}

<div class="page page_message">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="orcid.about.title"}
	<h2>
		{translate key="orcid.about.title"}
	</h2>
	<div class="description">
		{translate key="orcid.about.orcidExplanation"}
	</div>
	<h3>{translate key="orcid.about.howAndWhy.title"}</h3>
	{if $isMemberApi}
	<div class="description">
		{translate key="orcid.about.howAndWhyMemberAPI"}
	</div>
	{else}
		{translate key="orcid.about.howAndWhyPublicAPI"}
	{/if}
	<h3>{translate key="orcid.about.display.title"}</h3>
	<div class="description">
		{translate key="orcid.about.display"}
	</div>
</div>

{include file="frontend/components/footer.tpl"}
