{**
 * templates/admin/settings.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Administration settings page.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="admin.siteSettings"}
	</h1>

	{if $newVersionAvailable}
		<notification>
			{translate key="site.upgradeAvailable.admin" currentVersion=$currentVersion->getVersionString(false) latestVersion=$latestVersion}
		</notification>
	{/if}

	<tabs :track-history="true">
		{if $componentAvailability['siteSetup']}
		<tab id="setup" label="{translate key="admin.siteSetup"}">
			<tabs :is-side-tabs="true" :track-history="true">
				{if $componentAvailability['siteConfig']}
				<tab id="settings" label="{translate key="admin.settings"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_SITE_CONFIG}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['siteInfo']}
				<tab id="info" label="{translate key="manager.setup.information"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_SITE_INFO}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['languages']}
				<tab id="languages" label="{translate key="common.languages"}">
					{capture assign=languagesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="languageGridContainer" url=$languagesUrl}
				</tab>
				{/if}
				{if $componentAvailability['navigationMenus']}
				<tab id="nav" label="{translate key="manager.navigationMenus"}">
					{capture assign=navigationMenusGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="navigationMenuGridContainer" url=$navigationMenusGridUrl}
					{capture assign=navigationMenuItemsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="navigationMenuItemsGridContainer" url=$navigationMenuItemsGridUrl}
				</tab>
				{/if}
				{if $componentAvailability['bulkEmails']}
				<tab id="bulkEmails" label="{translate key="admin.settings.enableBulkEmails.label"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_SITE_BULK_EMAILS}"
						@set="set"
					/>
				</tab>
				{/if}
				{call_hook name="Template::Settings::admin::setup"}
			</tabs>
		</tab>
		{/if}
		{if $componentAvailability['siteAppearance']}
		<tab id="appearance" label="{translate key="manager.website.appearance"}">
			<tabs :is-side-tabs="true" :track-history="true">
				{if $componentAvailability['siteTheme']}
				<tab id="theme" label="{translate key="manager.setup.theme"}">
					<theme-form
						v-bind="components.{$smarty.const.FORM_THEME}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['siteAppearanceSetup']}
				<tab id="setup" label="{translate key="navigation.setup"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_SITE_APPEARANCE}"
						@set="set"
					/>
				</tab>
				{/if}
				{call_hook name="Template::Settings::admin::appearance"}
			</tabs>
		</tab>
		{/if}
		{if $componentAvailability['sitePlugins']}
		<tab id="plugins" label="{translate key="common.plugins"}">
			{capture assign=pluginGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.plugins.AdminPluginGridHandler" op="fetchGrid" escape=false}{/capture}
			{load_url_in_div id="pluginGridContainer" url=$pluginGridUrl}
		</tab>
		{/if}
		{call_hook name="Template::Settings::admin"}
	</tabs>
{/block}
