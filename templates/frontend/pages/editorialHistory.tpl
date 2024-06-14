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
			<ul>
				{foreach from=$mastheadUsers[$mastheadRole->getId()] item="mastheadUser"}
					<li>
						<ul id="commaList">
							<li>{$mastheadUser['user']->getFullName()|escape}</li>
							{if !empty($mastheadUser['user']->getLocalizedData('affiliation'))}
								<li>{$mastheadUser['user']->getLocalizedData('affiliation')|escape}</li>
							{/if}
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
							<li>
								{foreach from=$mastheadUser['services'] item="service"}
									<ul>
										<li>{translate key="common.fromUntil" from=$service['dateStart'] until=$service['dateEnd']}</li>
									</ul>
								{/foreach}
							</li>
						</ul>
					</li>
				{/foreach}
			</ul>
		{/if}
	{/foreach}

	{include file="frontend/components/editLink.tpl" page="management" op="settings" path="context" anchor="masthead" sectionTitleKey="common.editorialHistory"}
	{$currentContext->getLocalizedData('editorialHistory')}

</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
