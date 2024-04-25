{**
 * templates/frontend/pages/editorialHistory.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display journal's editorial history page.
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="common.editorialHistory"}

<div class="page page_masthead">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="common.editorialHistory"}

	<h1>{translate key="common.editorialHistory"}</h1>
	{foreach from=$mastheadRoles item="mastheadRole"}
		{if array_key_exists($mastheadRole->getId(), $mastheadUsers)}
			<h2>{$mastheadRole->getLocalizedName()|escape}</h2>
			<ul>
			{foreach from=$mastheadUsers[$mastheadRole->getId()] item="mastheadUser"}
				{foreach from=$mastheadUser['services'] item="service"}
					<li>
						{$mastheadUser['user']->getFullName()|escape},
						{$mastheadUser['user']->getLocalizedData('affiliation')|escape},
						{$service['dateStart']} - {$service['dateEnd']}
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
			{/foreach}
			</ul>
		{/if}
	{/foreach}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
