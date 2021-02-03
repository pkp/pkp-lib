{**
 * templates/admin/contextSettings.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Admin page for configuring high-level details about a context.
 *
 * @uses $editContext Context The context that is being edited.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.settings.wizard"}
	</h1>

	<tabs :track-history="true">
		<tab id="setup" label="{translate key="manager.setup"}">
			<tabs :is-side-tabs="true" :track-history="true">
				<tab id="context" label="{translate key="context.context"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_CONTEXT}"
						@set="set"
					/>
				</tab>
				<tab id="appearance" label="{translate key="manager.website.appearance"}">
					<theme-form
						v-bind="components.{$smarty.const.FORM_THEME}"
						@set="set"
					/>
				</tab>
				<tab id="languages" label="{translate key="common.languages"}">
					{capture assign=languagesUrl}{url router=$smarty.const.ROUTE_COMPONENT context=$editContext->getPath() component="grid.settings.languages.ManageLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="languageGridContainer" url=$languagesUrl}
				</tab>
				<tab id="indexing" label="{translate key="manager.setup.searchEngineIndexing"}">
					<pkp-form
						v-bind="components.{$smarty.const.FORM_SEARCH_INDEXING}"
						@set="set"
					/>
				</tab>
				<tab id="restrictBulkEmails" label="{translate key="admin.settings.restrictBulkEmails"}">
					{if $bulkEmailsEnabled}
						<pkp-form
							v-bind="components.{$smarty.const.FORM_RESTRICT_BULK_EMAILS}"
							@set="set"
						/>
					{else}
						{capture assign="siteSettingsUrl"}{url router=$smarty.const.ROUTE_PAGE page="admin" op="settings" anchor="setup/bulkEmails"}{/capture}
						<p>{translate key="admin.settings.disableBulkEmailRoles.contextDisabled" siteSettingsUrl=$siteSettingsUrl}</p>
					{/if}
				</tab>
				{call_hook name="Template::Settings::admin::contextSettings::setup"}
			</tabs>
		</tab>
		<tab id="plugins" label="{translate key="common.plugins"}">
			<tabs :track-history="true">
				<tab id="installed" label="{translate key="manager.plugins.installed"}">
					{capture assign=pluginGridUrl}{url router=$smarty.const.ROUTE_COMPONENT context=$editContext->getPath() component="grid.settings.plugins.SettingsPluginGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="pluginGridContainer" url=$pluginGridUrl}
				</tab>
				<tab id="gallery" label="{translate key="manager.plugins.pluginGallery"}">
					{capture assign=pluginGalleryGridUrl}{url router=$smarty.const.ROUTE_COMPONENT context=$editContext->getPath() component="grid.plugins.PluginGalleryGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="pluginGalleryGridContainer" url=$pluginGalleryGridUrl}
				</tab>
				{call_hook name="Template::Settings::admin::contextSettings::plugins"}
			</tabs>
		</tab>
		<tab id="users" label="{translate key="manager.users"}">
			{capture assign=usersUrl}{url router=$smarty.const.ROUTE_COMPONENT context=$editContext->getPath() component="grid.settings.user.UserGridHandler" op="fetchGrid" escape=false}{/capture}
			{load_url_in_div id="userGridContainer" url=$usersUrl}
		</tab>
		{call_hook name="Template::Settings::admin::contextSettings"}
	</tabs>
{/block}
