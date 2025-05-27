{**
 * templates/frontend/pages/indexServer.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display the index page for a server
 *
 * @uses $currentServer Server This server
 * @uses $serverDescription string Server description from HTML text editor
 * @uses $homepageImage object Image to be displayed on the homepage
 * @uses $additionalHomeContent string Arbitrary input from HTML text editor
 * @uses $announcements array List of announcements
 * @uses $numAnnouncementsHomepage int Number of announcements to display on the
 *       homepage
 *
 * @hook Templates::Index::server []
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$currentServer->getLocalizedName()}

<div class="page_index_server">

	{call_hook name="Templates::Index::server"}

	{if $highlights->count()}
		{include file="frontend/components/highlights.tpl" highlights=$highlights}
	{/if}

	{if $activeTheme && !$activeTheme->getOption('useHomepageImageAsHeader') && $homepageImage}
		<img src="{$publicFilesDir}/{$homepageImage.uploadName|escape:"url"}" alt="{$homepageImageAltText|escape}">
	{/if}

	{* Search and category listing *}
	{include file="frontend/components/archiveHeader.tpl"}

	{* Latest preprints *}
	<section class="homepage_latest_preprints">
		<h2>{translate key="index.latestPreprints"}</h2>
		<ul class="cmp_preprint_list preprints">
			{foreach from=$publishedSubmissions item="preprint"}
				<li>
					{include file="frontend/objects/preprint_summary.tpl" heading="h3"}
				</li>
			{/foreach}
		</ul>
	</section>

	{* Server Description *}
	{if $activeTheme && $activeTheme->getOption('showDescriptionInServerIndex')}
		<section class="homepage_about">
			<a id="homepageAbout"></a>
			<h2>{translate key="about.aboutContext"}</h2>
			{$currentContext->getLocalizedData('description')}
		</section>
	{/if}

	{include file="frontend/objects/announcements_list.tpl" numAnnouncements=$numAnnouncementsHomepage}

	{* Additional Homepage Content *}
	{if $additionalHomeContent}
		<div class="additional_content">
			{$additionalHomeContent}
		</div>
	{/if}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
