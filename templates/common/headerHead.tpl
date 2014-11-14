{**
 * templates/common/headerHead.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site header <head> tag and contents.
 *}
<head>
	<meta http-equiv="Content-Type" content="text/html; charset={$defaultCharset|escape}" />
	<title>{$pageTitleTranslated|strip_tags}</title>
	<meta name="description" content="{$metaSearchDescription|escape}" />
	<meta name="keywords" content="{$metaSearchKeywords|escape}" />
	<meta name="generator" content="{$applicationName} {$currentVersionString|escape}" />
	{$metaCustomHeaders}
	{if $displayFavicon}<link rel="icon" href="{$faviconDir}/{$displayFavicon.uploadName|escape:"url"}" type="{$displayFavicon.mimeType|escape}" />{/if}

	<!-- Base Jquery -->
	{if $allowCDN}
		<script src="//ajax.googleapis.com/ajax/libs/jquery/{$smarty.const.CDN_JQUERY_VERSION}/jquery.min.js"></script>
		<script src="//ajax.googleapis.com/ajax/libs/jqueryui/{$smarty.const.CDN_JQUERY_UI_VERSION}/jquery-ui.min.js"></script>
	{else}
		<script src="{$baseUrl}/lib/pkp/js/lib/jquery/jquery.min.js"></script>
		<script src="{$baseUrl}/lib/pkp/js/lib/jquery/plugins/jqueryUi.min.js"></script>
	{/if}

	<!-- UI elements (menus, forms, etc) -->
	<script src="{$baseUrl}/lib/pkp/js/lib/superfish/hoverIntent.js"></script>
	<script src="{$baseUrl}/lib/pkp/js/lib/superfish/superfish.js"></script>

	{include file="common/validate.tpl"}
	{include file="common/plupload.tpl"}

	{foreach from=$stylesheets item=styleSheetList}{* For all priority sets STYLE_PRIORITY_... *}
		{foreach from=$styleSheetList item=cssUrl}{* For all stylesheet URLs within this priority set *}
			<link rel="stylesheet" href="{$cssUrl}" type="text/css" />
		{/foreach}
	{/foreach}

	<!-- Constants for JavaScript -->
	{include file="common/jsConstants.tpl"}

	<!-- Default global locale keys for JavaScript -->
	{include file="common/jsLocaleKeys.tpl" }

	<!-- Compiled scripts -->
	{if $useMinifiedJavaScript}
		<script src="{$baseUrl}/js/pkp.min.js"></script>
	{else}
		{include file="common/minifiedScripts.tpl"}
	{/if}

	<!-- Pines Notify build/cache -->
	<script src="{$baseUrl}/lib/pkp/js/lib/pnotify/buildcustom.php?mode=js{if !$useMinifiedJavaScript}&min=1{/if}&modules="></script>

	{$deprecatedJavascript}

	{$deprecatedThemeStyles}

	{$additionalHeadData}
</head>
