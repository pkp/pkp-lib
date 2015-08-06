{**
 * templates/common/headerHead.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site frontend header <head> tag and contents.
 *}
<head>
	<meta http-equiv="Content-Type" content="text/html; charset={$defaultCharset|escape}" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$pageTitleTranslated|strip_tags}</title>
	<meta name="description" content="{$metaSearchDescription|escape}" />
	<meta name="keywords" content="{$metaSearchKeywords|escape}" />
	<meta name="generator" content="{$applicationName} {$currentVersionString|escape}" />
	{$metaCustomHeaders}
	{if $displayFavicon}<link rel="icon" href="{$faviconDir}/{$displayFavicon.uploadName|escape:"url"}" type="{$displayFavicon.mimeType|escape}" />{/if}

	<!-- Base Jquery -->
	{if $allowCDN}
		<script src="//ajax.googleapis.com/ajax/libs/jquery/{$smarty.const.CDN_JQUERY_VERSION}/{if $useMinifiedJavaScript}jquery.min.js{else}jquery.js{/if}"></script>
		<script src="//ajax.googleapis.com/ajax/libs/jqueryui/{$smarty.const.CDN_JQUERY_UI_VERSION}/{if $useMinifiedJavaScript}jquery-ui.min.js{else}jquery-ui.js{/if}"></script>
	{else}
		<script src="{$baseUrl}/lib/pkp/lib/vendor/components/jquery/{if $useMinifiedJavaScript}jquery.min.js{else}jquery.js{/if}"></script>
		<script src="{$baseUrl}/lib/pkp/lib/vendor/components/jqueryui/{if $useMinifiedJavaScript}jquery-ui.min.js{else}jquery-ui.js{/if}"></script>
	{/if}

	{include file="common/validate.tpl"}
	{include file="common/plupload.tpl"}

	{load_stylesheet context="backend" stylesheets=$stylesheets}

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
	<script src="{$baseUrl}/lib/pkp/js/lib/pnotify/buildcustom.php?mode=js{if $useMinifiedJavaScript}&amp;min=1{/if}&amp;modules=buttons"></script>
	<link rel="stylesheet" href="{$baseUrl}/lib/pkp/js/lib/pnotify/buildcustom.php?mode=css{if $useMinifiedJavaScript}&amp;min=1{/if}&amp;modules=buttons"/>

	{$deprecatedJavascript}

	{$deprecatedThemeStyles}

	{$additionalHeadData}
</head>
