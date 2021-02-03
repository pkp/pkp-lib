{**
 * templates/management/access.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The users, roles and site access settings page.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="navigation.access"}
	</h1>

	<tabs :track-history="true">
		<tab id="users" label="{translate key="manager.users"}">
			{include file="management/accessUsers.tpl"}
		</tab>
		<tab id="roles" label="{translate key="manager.roles"}">
			{help file="users-and-roles" section="roles" class="pkp_help_tab"}
			{capture assign=rolesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.roles.UserGroupGridHandler" op="fetchGrid" escape=false}{/capture}
			{load_url_in_div id="roleGridContainer" url=$rolesUrl}
		</tab>
		{if $enableBulkEmails}
		<tab id="notify" label="{translate key="manager.setup.notifyUsers"}">
			<div v-if="queueId" role="alert">
				<p v-if="completedJobs < totalJobs">
					<spinner class="notifyUsers__progress__spinner"></spinner>
					{translate key="manager.setup.notifyUsers.sending"}
				</p>
				<p v-else>
					<icon icon="check" :inline="true"></icon>
					{translate key="manager.setup.notifyUsers.sent"}
					<button class="-linkButton" @click="reload">
						{translate key="manager.setup.notifyUsers.sendAnother"}
					</button>
				</p>
				<progress-bar :max="totalJobs" :min="0" :value="completedJobs" />
			</div>
			<notify-users-form v-else
				v-bind="components.{$smarty.const.FORM_NOTIFY_USERS}"
				@set="set"
			/>
		</tab>
		{/if}
		<tab id="access" label="{translate key="manager.siteAccessOptions.siteAccessOptions"}">
		{help file="users-and-roles" section="site-access" class="pkp_help_tab"}
			<pkp-form
				v-bind="components.{$smarty.const.FORM_USER_ACCESS}"
				@set="set"
			/>
		</tab>
		{call_hook name="Template::Settings::access"}
	</tabs>
{/block}
