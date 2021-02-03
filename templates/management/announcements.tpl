{**
 * templates/management/announcements.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Add and edit announcements and announcement types
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.setup.announcements"}
	</h1>

	<tabs :track-history="true">
		<tab id="announcements" label="{translate key="manager.setup.announcements"}">
			<announcements-list-panel
				v-bind="components.announcements"
				@set="set"
			/>
		</tab>
		<tab id="announcementTypes" label="{translate key="manager.announcementTypes"}">
			{capture assign=announcementTypeGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.AnnouncementTypeGridHandler" op="fetchGrid" escape=false}{/capture}
			{load_url_in_div id="announcementTypeGridContainer" url=$announcementTypeGridUrl}
		</tab>
		{call_hook name="Template::Announcements"}
	</tabs>
{/block}
