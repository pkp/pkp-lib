{**
 * templates/frontend/pages/editorialHistory.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display context's editorial history page.
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="common.editorialHistory"}

<div class="page page_masthead">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="common.editorialHistory"}

	<h1>{translate key="common.editorialHistory.page"}</h1>
	<p>{translate key="common.editorialHistory.page.description"}</p>
	{foreach from=$mastheadRoles item="mastheadRole"}
		{if array_key_exists($mastheadRole->getId(), $mastheadUsers)}
			<h2>{$mastheadRole->getLocalizedName()|escape}</h2>
			<ul class="user_listing" role="list">
				{foreach from=$mastheadUsers[$mastheadRole->getId()] item="mastheadUser"}
					<li>
						{strip}
						<span class="date_start">
							{foreach name="services" from=$mastheadUser['services'] item="service"}
								{translate key="common.fromUntil" from=$service['dateStart'] until=$service['dateEnd']}
								{if !$smarty.foreach.services.last}{translate key="common.commaListSeparator"}{/if}
							{/foreach}	
						</span>
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

	{include file="frontend/components/editLink.tpl" page="management" op="settings" path="context" anchor="masthead" sectionTitleKey="common.editorialHistory"}
	{$currentContext->getLocalizedData('editorialHistory')}

</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
