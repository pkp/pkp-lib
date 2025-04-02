{**
 * lib/pkp/templates/layouts/backend.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Common site header.
 *
 * @hook Template::Layout::Backend::HeaderActions []
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset={$defaultCharset|escape}" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{title|strip_tags value=$pageTitle}</title>
	{load_header context="backend"}
	{load_stylesheet context="backend"}
	{load_script context="backend"}
	<style type="text/css">
		/* Prevent flash of unstyled content in some browsers */
		[v-cloak] { display: none; }
	</style>
</head>
<body class="pkp_page_{$requestedPage|escape|default:"index"} pkp_op_{$requestedOp|escape|default:"index"}" dir="{$currentLocaleLangDir|escape|default:"ltr"}">

	<script type="text/javascript">
		// Initialise JS handler.
		$(function() {ldelim}
			$('body').pkpHandler(
				'$.pkp.controllers.SiteHandler',
				{ldelim}
					{include file="controllers/notification/notificationOptions.tpl"}
				{rdelim});
		{rdelim});
	</script>
	<div id="app" class="app" v-cloak>
		<pkp-spinner-full-screen></pkp-spinner-full-screen>
		<vue-announcer class="sr-only"></vue-announcer>
		<pkp-announcer class="sr-only"></pkp-announcer>
		<modal-manager></modal-manager>
		<header class="app__header" role="banner">
			{if $availableContexts}
				<dropdown class="app__headerAction app__contexts">
					<template #button>
						<icon icon="Sitemap" class="h-7 w-7"></icon>
						<span class="-screenReader">{translate key="context.contexts"}</span>
					</template>
					<ul>
						{foreach from=$availableContexts item=$availableContext}
							{if !$currentContext || $availableContext->name !== $currentContext->getLocalizedData('name')}
								<li>
									<a href="{$availableContext->url|escape}" class="pkpDropdown__action">
										{$availableContext->name|escape}
									</a>
								</li>
							{/if}
						{/foreach}
					</ul>
				</dropdown>
			{/if}
			{if $currentContext}
				<a class="app__contextTitle" href="{url page="index"}">
					{$currentContext->getLocalizedData('name')|escape}
				</a>
			{elseif $siteTitle}
				<a class="app__contextTitle" href="{$baseUrl}">
					{$siteTitle|escape}
				</a>
			{else}
				<div class="app__contextTitle">
					{translate key="common.software"}
				</div>
			{/if}
			{if $currentUser}
				{call_hook name="Template::Layout::Backend::HeaderActions"}
				<top-nav-actions></top-nav-actions>
			{/if}
		</header>

		<div class="app__body">
			{block name="menu"}
				{if isset($currentContext) && isset($currentUser) && $currentUser->getRoles($currentContext->getId())|count > 0}
					<pkp-side-nav :links="menu" aria-label="{translate key="common.navigation.site"}">
					</pkp-side-nav>
				{/if}
			{/block}
			<main class="app__main">
				<div class="app__page width{if $pageWidth} width--{$pageWidth}{/if}">
					{block name="breadcrumbs"}
						{if $breadcrumbs}
							<nav class="app__breadcrumbs" role="navigation" aria-label="{translate key="navigation.breadcrumbLabel"}">
								<ol>
									{foreach from=$breadcrumbs item="breadcrumb" name="breadcrumbs"}
										{assign var=_format value=$breadcrumb.format|default:'text'|lower}

										{if $_format === 'text'}
											{assign var=_name value=$breadcrumb.name|escape}
										{else}
											{assign var=_name value=$breadcrumb.name|strip_unsafe_html}
										{/if}

										<li>
											{if $smarty.foreach.breadcrumbs.last}
												<span aria-current="page">
													{$_name}
												</span>
											{else}
												<a href="{$breadcrumb.url|escape}">
													{$_name}
												</a>
												<span class="app__breadcrumbsSeparator" aria-hidden="true">{translate key="navigation.breadcrumbSeparator"}</span>
											{/if}
										</li>
									{/foreach}
								</ol>
							</nav>
						{/if}
					{/block}

					{block name="page"}{/block}

				</div>
			</main>
		</div>
		<div
			aria-live="polite"
			aria-atomic="true"
			class="app__notifications"
			ref="notifications"
			role="status"
		>
			<transition-group name="app__notification">
				<notification v-for="notification in notifications" :key="notification.key" :type="notification.type" :can-dismiss="true" @dismiss="dismissNotification(notification.key)">
					{{ notification.message }}
				</notification>
			</transition-group>
		</div>
		<transition name="app__loading">
			<div
				v-if="isLoading"
				class="app__loading"
				role="alert"
			>
				<div class="app__loading__content">
					<spinner></spinner>
					{translate key="common.loading"}
				</div>
			</div>
		</transition>
	</div>

	<script type="text/javascript">
		pkp.registry.init('app', {$pageComponent|json_encode}, {$state|json_encode});
	</script>

	<script type="text/javascript">
		// Initialize JS handler
		$(function() {ldelim}
			$('#pkpHelpPanel').pkpHandler(
				'$.pkp.controllers.HelpPanelHandler',
				{ldelim}
					helpUrl: {url|json_encode page="help" escape=false},
					helpLocale: '{$currentLocale|substr:0:2}',
				{rdelim}
			);
		{rdelim});
	</script>
	<div id="pkpHelpPanel" class="pkp_help_panel" tabindex="-1">
		<div class="panel">
			<div class="header">
				<a href="#" class="pkpHomeHelpPanel home">
					{translate key="help.toc"}
				</a>
				<a href="#" class="pkpCloseHelpPanel close">
					{translate key="common.close"}
				</a>
			</div>
			<div class="content">
				{include file="common/loadingContainer.tpl"}
			</div>
			<div class="footer">
				<a href="#" class="pkpPreviousHelpPanel previous">
					{translate key="help.previous"}
				</a>
				<a href="#" class="pkpNextHelpPanel next">
					{translate key="help.next"}
				</a>
			</div>
		</div>
	</div>

</body>
</html>
