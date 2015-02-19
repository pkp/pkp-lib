{**
 * templates/controllers/page/header.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site header div contents.
 *}
<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#headerTemplateContainer').pkpHandler(
			'$.pkp.pages.header.HeaderHandler',
			{ldelim}
				requestedPage: '{$requestedPage|escape:"javascript"}',
				fetchUnreadNotificationsCountUrl: '{url|escape:javascript router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.NotificationsGridHandler" op="getUnreadNotificationsCount"}'
			{rdelim});
	{rdelim});
</script>
<div class="pkp_structure_content" id="headerTemplateContainer">
	<div class="unit size1of5">
		<div class="pkp_structure_masthead">
				{if $displayPageHeaderLogo && is_array($displayPageHeaderLogo)}
					<h1><a href="{$homeUrl}"><img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}" width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}" {if $displayPageHeaderLogoAltText != ''}alt="{$displayPageHeaderLogoAltText|escape}"{else}alt="{translate key="common.pageHeaderLogo.altText"}"{/if} /></a></h1>
				{elseif $displayPageHeaderTitle && is_array($displayPageHeaderTitle)}
					<h1><a href="{$homeUrl}"><img src="{$publicFilesDir}/{$displayPageHeaderTitle.uploadName|escape:"url"}" width="{$displayPageHeaderTitle.width|escape}" height="{$displayPageHeaderTitle.height|escape}" {if $displayPageHeaderTitleAltText != ''}alt="{$displayPageHeaderTitleAltText|escape}"{else}alt="{translate key="common.pageHeader.altText"}"{/if} /></a></h1>
				{elseif $displayPageHeaderTitle}
					<h1 class="pkp_helpers_text_center pkp_helpers_title_padding"><a href="{$homeUrl}">{$displayPageHeaderTitle}</a></h1>
				{elseif $alternatePageHeader}
					<h1 class="pkp_helpers_text_center pkp_helpers_title_padding"><a href="{$homeUrl}">{$alternatePageHeader}</a></h1>
				{else}
					<a href="{$homeUrl}"><img src="{$baseUrl}/{$logoImage}" alt="{$applicationName|escape}" title="{$applicationName|escape}" width="180" height="90" /></a>
				{/if}
		</div><!-- pkp_structure_masthead -->
	</div>
	<div class="unit size4of5">
		<div class="pkp_structure_navigation">
			{include file="header/sitenav.tpl"}
			{include file="header/localnav.tpl"}
		</div>
	</div>
</div><!-- pkp_structure_content -->
