{**
 * templates/frontend/pages/editorialMasthead.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display context's editorial masthead page.
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="common.editorialMasthead"}

<div class="page page_masthead">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="common.editorialMasthead"}

	<h1>{translate key="common.editorialMasthead"}</h1>
	{foreach from=$mastheadRoles item="mastheadRole"}
		{if array_key_exists($mastheadRole->getId(), $mastheadUsers)}
			<h2>{$mastheadRole->getLocalizedName()|escape}</h2>
			<ul>
			{foreach from=$mastheadUsers[$mastheadRole->getId()] item="mastheadUser"}
				<li>
					<ul id="commaList">
						<li>{$mastheadUser['user']->getFullName()|escape}</li>
						{if !empty($mastheadUser['user']->getLocalizedData('affiliation'))}
							<li>{$mastheadUser['user']->getLocalizedData('affiliation')|escape}</li>
						{/if}
						<li>{$mastheadUser['dateStart']}</li>
					</ul>
					{if $mastheadUser['user']->getData('orcid')}
						<span class="orcid">
							{if $mastheadUser['user']->getData('orcidAccessToken')}
								{$orcidIcon}
							{/if}
							<a href="{$mastheadUser['user']->getData('orcid')|escape}" target="_blank">
							{$mastheadUser['user']->getData('orcid')|escape}
							</a>
						</span>
					{/if}
				</li>
			{/foreach}
			</ul>
		{/if}
	{/foreach}

	<p>
		{capture assign=editorialHistoryUrl}{url page="about" op="editorialHistory" router=\PKP\core\PKPApplication::ROUTE_PAGE}{/capture}
		{translate key="about.editorialMasthead.linkToEditorialHistory" url=$editorialHistoryUrl}
	</p>

	{if !empty($reviewers)}
		<h2>{translate key="common.editorialMasthead.peerReviewers"}</h2>
		<p>{translate key="common.editorialMasthead.peerReviewers.description" year=$previousYear}</p>
		<ul>
		{foreach from=$reviewers item="reviewer"}
			<li>
				<ul id="commaList">
					<li>{$reviewer->getFullName()|escape}</li>
					{if !empty($reviewer->getLocalizedData('affiliation'))}
						<li>{$reviewer->getLocalizedData('affiliation')|escape}</li>
					{/if}
				</ul>
				{if $reviewer->getData('orcid')}
					<span class="orcid">
						{if $reviewer->getData('orcidAccessToken')}
							{$orcidIcon}
						{/if}
						<a href="{$reviewer->getData('orcid')|escape}" target="_blank">
						{$reviewer->getData('orcid')|escape}
					</a>
					</span>
				{/if}
			</li>
		{/foreach}
		</ul>
	{/if}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
