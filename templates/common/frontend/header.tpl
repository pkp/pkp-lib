{**
 * lib/pkp/templates/common/frontend/header.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common frontend site header.
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{if !$pageTitleTranslated}{translate|assign:"pageTitleTranslated" key=$pageTitle}{/if}
{include file="core:common/frontend/headerHead.tpl"}
<body class="pkp_page_{$requestedPage|escape} pkp_op_{$requestedOp|escape}">
	<script type="text/javascript">
		// Initialise JS handler.
		$(function() {ldelim}
			$('body').pkpHandler(
				'$.pkp.controllers.SiteHandler',
				{ldelim}
					{if $isUserLoggedIn}
						inlineHelpState: {$initialHelpState},
					{/if}
					toggleHelpUrl: '{url|escape:javascript page="user" op="toggleHelp"}',
					toggleHelpOnText: '{$toggleHelpOnText|escape:"javascript"}',
					toggleHelpOffText: '{$toggleHelpOffText|escape:"javascript"}',
					{include file="core:controllers/notification/notificationOptions.tpl"}
				{rdelim});
		{rdelim});
	</script>
	<div class="pkp_structure_page">

		{* Header wrapper *}
		<header class="pkp_structure_head">
			<nav class="pkp_navigation" id="headerNavigationContainer">

				{* Logo or site title *}
				<h1>
					{if $displayPageHeaderLogo && is_array($displayPageHeaderLogo)}
						<a href="{$homeUrl}">
							<img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}" width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}" {if $displayPageHeaderLogoAltText != ''}alt="{$displayPageHeaderLogoAltText|escape}"{else}alt="{translate key="common.pageHeaderLogo.altText"}"{/if} />
						</a>
					{elseif $displayPageHeaderTitle && is_array($displayPageHeaderTitle)}
						<a href="{$homeUrl}">
							<img src="{$publicFilesDir}/{$displayPageHeaderTitle.uploadName|escape:"url"}" width="{$displayPageHeaderTitle.width|escape}" height="{$displayPageHeaderTitle.height|escape}" {if $displayPageHeaderTitleAltText != ''}alt="{$displayPageHeaderTitleAltText|escape}"{else}alt="{translate key="common.pageHeader.altText"}"{/if} />
						</a>
					{elseif $displayPageHeaderTitle}
						<a href="{$homeUrl}">{$displayPageHeaderTitle}</a>
					{elseif $alternatePageHeader}
						<a href="{$homeUrl}">{$alternatePageHeader}</a>
					{else}
						<a href="{$homeUrl}">
							<img src="{$baseUrl}/{$logoImage}" alt="{$applicationName|escape}" title="{$applicationName|escape}" width="180" height="90" />
						</a>
					{/if}
				</h1>

				{* Primary site navigation *}
				<ul id="navigationPrimary" class="pkp_navigation_primary pkp_nav_list">

					{if $enableAnnouncements}
						<li>
							<a href="{url router=$smarty.const.ROUTE_PAGE page="announcement"}">
								{translate key="announcement.announcements"}
							</a>
						</li>
					{/if}

					{if $currentJournal}

						{if $currentJournal->getSetting('publishingMode') != $smarty.const.PUBLISHING_MODE_NONE}
							<li>
								<a href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="current"}">
									{translate key="navigation.current"}
								</a>
							</li>
							<li>
								<a href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}">
									{translate key="navigation.archives"}
								</a>
							</li>
						{/if}

						<li class="has-submenu"><a href="#">{translate key="navigation.about"}</a>
							<ul>
								{if not (empty($contextSettings.mailingAddress) && empty($contextSettings.contactName) && empty($contextSettings.contactAffiliation) && empty($contextSettings.contactMailingAddress) && empty($contextSettings.contactPhone) && empty($contextSettings.contactFax) && empty($contextSettings.contactEmail) && empty($contextSettings.supportName) && empty($contextSettings.supportPhone) && empty($contextSettings.supportEmail))}
									<li>
										<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="contact"}">
											{translate key="about.contact"}
										</a>
									</li>
								{/if}
								<li>
									<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="description"}">
										{translate key="about.description"}
									</a>
								</li>
								<li>
									<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="editorialTeam"}">
										{translate key="about.editorialTeam"}
									</a>
								</li>
								<li>
									<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="editorialPolicies"}">
										{translate key="about.policies"}
									</a>
								</li>
								<li>
									<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="submissions"}">
										{translate key="about.submissions"}
									</a>
								</li>
								{if not ($currentJournal->getLocalizedSetting('contributorNote') == '' && empty($contextSettings.contributors) && $currentJournal->getLocalizedSetting('sponsorNote') == '' && empty($contextSettings.sponsors))}
									<li>
										<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="sponsorship"}">
											{translate key="about.journalSponsorship"}
										</a>
									</li>
								{/if}
							</ul>
						</li>
					{/if}
				</ul>

				{* User-specific login, settings and task management *}
				{url|assign:fetchHeaderUrl router=$smarty.const.ROUTE_COMPONENT component="page.PageHandler" op="header" escape=false}
				{load_url_in_div class="pkp_wrapper_user_nav" id="userNavContainer" url=$fetchHeaderUrl}

			</nav><!-- pkp_navigation -->
		</header><!-- .pkp_structure_head -->

		{* Main page body wrapper *}
		<div class="pkp_structure_body">
			<div class="pkp_structure_content">
				<div class="line">
					{if !$noContextsConfigured}
						{include file="header/search.tpl"}
					{/if}
				</div>


				<script type="text/javascript">
					// Attach the JS page handler to the main content wrapper.
					$(function() {ldelim}
						$('div.pkp_structure_main').pkpHandler('$.pkp.controllers.PageHandler');
					{rdelim});
				</script>

				<div class="pkp_structure_main">
					{** allow pages to provide their own titles **}
					{if !$suppressPageTitle}
						<h2 class="title_left">{$pageTitleTranslated}</h2>
					{/if}
