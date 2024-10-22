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
			<ul class="user_listing" role="list">
			{foreach from=$mastheadUsers[$mastheadRole->getId()] item="mastheadUser"}
				<li>
					{strip}
						<span class="date_start">{translate key="common.fromUntil" from=$mastheadUser['dateStart'] until=""}</span>
						<span class="name">
							{$mastheadUser['user']->getFullName()|escape}
							{if $mastheadUser['user']->getData('orcid') && $mastheadUser['user']->getData('orcidAccessToken')}
								<span class="orcid">	
									<a href="{$mastheadUser['user']->getData('orcid')|escape}" target="_blank" aria-label="{translate key="common.editorialHistory.page.orcidLink" name=$mastheadUser['user']->getFullName()|escape}">
										{$orcidIcon}
									</a>
								</span>
							{/if}
						</span>
						{if !empty($mastheadUser['user']->getLocalizedData('affiliation'))}
							<span class="affiliation">{$mastheadUser['user']->getLocalizedData('affiliation')|escape}</span>
						{/if}
					{/strip}
				</li>
			{/foreach}
			</ul>
		{/if}
	{/foreach}
	<hr>
	<p>
		{capture assign=editorialHistoryUrl}{url page="about" op="editorialHistory" router=\PKP\core\PKPApplication::ROUTE_PAGE}{/capture}
		{translate key="about.editorialMasthead.linkToEditorialHistory" url=$editorialHistoryUrl}
	</p>

	{if !empty($reviewers)}
		<h2>{translate key="common.editorialMasthead.peerReviewers"}</h2>
		<p>{translate key="common.editorialMasthead.peerReviewers.description" year=$previousYear}</p>
		<ul class="user_listing" role="list">
		{foreach from=$reviewers item="reviewer"}
			<li>
				{strip}
					<span class="name">
						{$reviewer->getFullName()|escape} 
						{if $reviewer->getData('orcid') && $reviewer->getData('orcidAccessToken')}
							<span class="orcid">	
								<a href="{$reviewer->getData('orcid')|escape}" target="_blank" aria-label="{translate key="common.editorialHistory.page.orcidLink" name=$reviewer->getFullName()|escape}">
									{$orcidIcon}
								</a>
							</span>
						{/if}
					</span>
					{if !empty($reviewer->getLocalizedData('affiliation'))}
						<span class="affiliation">{$reviewer->getLocalizedData('affiliation')|escape}</span>
					{/if}
				{/strip}
			</li>
		{/foreach}
		</ul>
	{/if}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
