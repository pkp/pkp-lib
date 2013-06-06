{**
 * templates/header/index.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
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
			{rdelim});
	{rdelim});
</script>
<div class="pkp_structure_content" id="headerTemplateContainer">
	<div class="unit size1of5">
		<div class="pkp_structure_masthead">
			<h1>
				{if $displayPageHeaderLogo && is_array($displayPageHeaderLogo)}
					<a href="{$homeUrl}"><img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}" width="{$displayPageHeaderLogo.width|escape}" height="{$displayPageHeaderLogo.height|escape}" {if $displayPageHeaderLogoAltText != ''}alt="{$displayPageHeaderLogoAltText|escape}"{else}alt="{translate key="common.pageHeaderLogo.altText"}"{/if} /></a>
				{elseif $displayPageHeaderTitle && is_array($displayPageHeaderTitle)}
					<a href="{$homeUrl}"><img src="{$publicFilesDir}/{$displayPageHeaderTitle.uploadName|escape:"url"}" width="{$displayPageHeaderTitle.width|escape}" height="{$displayPageHeaderTitle.height|escape}" {if $displayPageHeaderTitleAltText != ''}alt="{$displayPageHeaderTitleAltText|escape}"{else}alt="{translate key="common.pageHeader.altText"}"{/if} /></a>
				{elseif $displayPageHeaderTitle}
					<a href="{$homeUrl}">{$displayPageHeaderTitle}</a>
				{elseif $alternatePageHeader}
					<a href="{$homeUrl}">{$alternatePageHeader}</a>
				{else}
					<a href="{$homeUrl}"><img src="{$baseUrl}/{$logoImage}" alt="{$applicationName|escape}" title="{$applicationName|escape}" width="180" height="90" /></a>
				{/if}
			</h1>
		</div><!-- pkp_structure_masthead -->
	</div>
	<div class="unit size4of5">
		<div class="pkp_structure_navigation">
			{include file="header/sitenav.tpl"}
			{include file="header/localnav.tpl"}
		</div>
	</div>
</div><!-- pkp_structure_content -->
