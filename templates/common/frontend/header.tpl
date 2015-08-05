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
		<header class="pkp_structure_head" id="headerNavigationContainer">

			<div class="pkp_site_name_wrapper">
				{* Logo or site title. Only use <h1> heading on the homepage.
				   Otherwise that should go to the page title. *}
				{if $requestedOp == 'index'}
					<h1 class="pkp_site_name">
				{else}
					<div class="pkp_site_name">
				{/if}
                    {if $currentJournal && $multipleContexts}
                        {url|assign:"homeUrl" journal="index" router=$smarty.const.ROUTE_PAGE}
                    {else}
                        {url|assign:"homeUrl" page="index" router=$smarty.const.ROUTE_PAGE}
                    {/if}
					{if $displayPageHeaderLogo && is_array($displayPageHeaderLogo)}
						<a href="{$homeUrl}" class="is_img">
							<img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}" width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}" {if $displayPageHeaderLogoAltText != ''}alt="{$displayPageHeaderLogoAltText|escape}"{else}alt="{translate key="common.pageHeaderLogo.altText"}"{/if} />
						</a>
					{elseif $displayPageHeaderTitle && is_array($displayPageHeaderTitle)}
						<a href="{$homeUrl}" class="is_img">
							<img src="{$publicFilesDir}/{$displayPageHeaderTitle.uploadName|escape:"url"}" width="{$displayPageHeaderTitle.width|escape}" height="{$displayPageHeaderTitle.height|escape}" {if $displayPageHeaderTitleAltText != ''}alt="{$displayPageHeaderTitleAltText|escape}"{else}alt="{translate key="common.pageHeader.altText"}"{/if} />
						</a>
					{elseif $displayPageHeaderTitle}
						<a href="{$homeUrl}" class="is_text">{$displayPageHeaderTitle}</a>
					{elseif $alternatePageHeader}
						{$alternatePageHeader}
					{else}
						<a href="{$homeUrl}" class="is_img">
							<img src="{$baseUrl}/templates/images/structure/ojs_logo.png" alt="{$applicationName|escape}" title="{$applicationName|escape}" width="180" height="90" />
						</a>
					{/if}
				{if $requestedOp == 'index'}
					</h1>
				{else}
					</div>
				{/if}
			</div>

			{* Primary site navigation *}
			<script type="text/javascript">
				// Attach the JS file tab handler.
				$(function() {ldelim}
					$('#navigationPrimary').pkpHandler(
						'$.pkp.controllers.MenuHandler');
				{rdelim});
			</script>
			<nav class="pkp_navigation_primary_row">
				<div class="pkp_navigation_primary_wrapper">
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

							<li class="has-submenu">
								<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="about"}">
									{translate key="navigation.about"}
								</a>
								<ul>
									<li>
										<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="editorialTeam"}">
											{translate key="about.editorialTeam"}
										</a>
									</li>
									<li>
										<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="submissions"}">
											{translate key="about.submissions"}
										</a>
									</li>
								</ul>
							</li>
						{/if}
					</ul>

					{* Search form *}
					{if !$noContextsConfigured}
						{include file="header/search.tpl"}
					{/if}
				</div>
			</nav>

			{* User-specific login, settings and task management *}
			{url|assign:fetchHeaderUrl router=$smarty.const.ROUTE_COMPONENT component="page.PageHandler" op="userNav" escape=false}
			{load_url_in_div class="pkp_navigation_user_wrapper" id="navigationUserWrapper" url=$fetchHeaderUrl}

		</header><!-- .pkp_structure_head -->

		{* Wrapper for page content and sidebars *}
		<div class="pkp_structure_content{if $hasLeftSidebar} has_left_sidebar{/if}{if $hasRightSidebar} has_right_sidebar{/if}">

			<script type="text/javascript">
				// Attach the JS page handler to the main content wrapper.
				$(function() {ldelim}
					$('div.pkp_structure_main').pkpHandler('$.pkp.controllers.PageHandler');
				{rdelim});
			</script>

			<div class="pkp_structure_main">
