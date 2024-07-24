{**
 * templates/admin/settings.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Administration settings page.
 *
 * @hook Template::Settings::admin::setup []
 * @hook Template::Settings::admin::appearance []
 * @hook Template::Settings::admin []
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
						v-bind="components.{PKP\components\forms\site\PKPSiteConfigForm::FORM_SITE_CONFIG}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['siteInfo']}
				<tab id="info" label="{translate key="manager.setup.information"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\site\PKPSiteInformationForm::FORM_SITE_INFO}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['languages']}
				<tab id="languages" label="{translate key="common.languages"}">
					{capture assign=languagesUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="languageGridContainer" url=$languagesUrl}
				</tab>
				{/if}
				{if $componentAvailability['navigationMenus']}
				<tab id="nav" label="{translate key="manager.navigationMenus"}">
					{capture assign=navigationMenusGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="navigationMenuGridContainer" url=$navigationMenusGridUrl}
					{capture assign=navigationMenuItemsGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="navigationMenuItemsGridContainer" url=$navigationMenuItemsGridUrl}
				</tab>
				{/if}
				{if $componentAvailability['highlights']}
				<tab id="highlights" label="{translate key="common.highlights"}">
					<highlights-list-panel
						v-bind="components.highlights"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['bulkEmails']}
				<tab id="bulkEmails" label="{translate key="admin.settings.enableBulkEmails.label"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\site\PKPSiteBulkEmailsForm::FORM_SITE_BULK_EMAILS}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['statistics']}
				<tab id="statistics" label="{translate key="manager.setup.statistics"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\site\PKPSiteStatisticsForm::FORM_SITE_STATISTICS}"
						@set="set"
					/>
				</tab>
				{/if}
                {if $componentAvailability['orcidSiteSettings']}
                    <tab id="orcidSiteSettings" label="{translate key="orcid.displayName"}">
                        <pkp-form
                            v-bind="components.orcidSiteSettings"
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
						v-bind="components.{PKP\components\forms\context\PKPThemeForm::FORM_THEME}"
						@set="set"
					/>
				</tab>
				{/if}
				{if $componentAvailability['siteAppearanceSetup']}
				<tab id="setup" label="{translate key="navigation.setup"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\site\PKPSiteAppearanceForm::FORM_SITE_APPEARANCE}"
						@set="set"
					/>
				</tab>
				{/if}
				{call_hook name="Template::Settings::admin::appearance"}
			</tabs>
		</tab>
		{/if}
		{if $componentAvailability['announcements']}
		<tab id="announcements" label="{translate key="announcement.announcements"}">
			<tabs :is-side-tabs="true" :track-history="true">
				<tab id="announcement-settings" label="{translate key="admin.settings"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\context\PKPAnnouncementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS}"
						@set="set"
					></pkp-form>
				</tab>
				<tab id="announcement-items" label="{translate key="announcement.announcements"}">
					<announcements-list-panel
						v-if="announcementsEnabled"
						v-bind="components.announcements"
						@set="set"
					></announcements-list-panel>
					<p v-else>
						{translate key="manager.announcements.notEnabled"}
					</p>
				</tab>
				<tab id="announcement-types" label="{translate key="manager.announcementTypes"}">
					<template v-if="announcementsEnabled">
						{capture assign=announcementTypeGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.announcements.AnnouncementTypeGridHandler" op="fetchGrid" escape=false}{/capture}
						{load_url_in_div id="announcementTypeGridContainer" url=$announcementTypeGridUrl inVueEl=true}
					</template>
					<p v-else>
						{translate key="manager.announcements.notEnabled"}
					</p>
				</tab>
			</tabs>
		</tab>
		{/if}
		{if $componentAvailability['sitePlugins']}
		<tab id="plugins" label="{translate key="common.plugins"}">
            <tabs :track-history="true">
                <tab id="installedPlugins" label="{translate key="manager.plugins.installed"}">
                    {capture assign=pluginGridUrl}{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.admin.plugins.AdminPluginGridHandler" op="fetchGrid" escape=false}{/capture}
                    {load_url_in_div id="pluginGridContainer" url=$pluginGridUrl}
                </tab>
                <tab id="pluginGallery" label="{translate key="manager.plugins.pluginGallery"}">
                    {capture assign=pluginGalleryGridUrl}{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.plugins.PluginGalleryGridHandler" op="fetchGrid" escape=false}{/capture}
                    {load_url_in_div id="pluginGalleryGridContainer" url=$pluginGalleryGridUrl}
                </tab>
            </tabs>
        </tab>
		{/if}
		{call_hook name="Template::Settings::admin"}
	</tabs>
{/block}
