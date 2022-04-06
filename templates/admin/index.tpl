{**
 * templates/admin/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Site administration index.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="navigation.admin"}
	</h1>

	{if $newVersionAvailable}
		<notification>
			{translate key="site.upgradeAvailable.admin" currentVersion=$currentVersion->getVersionString(false) latestVersion=$latestVersion}
		</notification>
	{/if}

	<action-panel>
		<h2>{translate key="admin.siteManagement"}</h2>
		<p>
			{translate key="admin.siteManagement.description"}
		</p>
		<template slot="actions">
			<pkp-button
				element="a"
				href="{url op="contexts"}"
			>
				{translate key="admin.hostedContexts"}
			</pkp-button>
			<pkp-button
				element="a"
				href="{url op="settings"}"
			>
				{translate key="admin.siteSettings"}
			</pkp-button>
		</template>
	</action-panel>
	<action-panel>
		<h2>{translate key="admin.systemInformation"}</h2>
		<p>
			{translate key="admin.systemInformation.description"}
		</p>
		<template slot="actions">
			<pkp-button
				element="a"
				href="{url op="systemInfo"}"
			>
				{translate key="admin.systemInformation.view"}
			</pkp-button>
		</template>
	</action-panel>
	<action-panel>
		<h2>{translate key="admin.expireSessions"}</h2>
		<p>
			{translate key="admin.expireSessions.description"}
		</p>
		<template slot="actions">
			<form type="post" action="{url op="expireSessions"}">
				{csrf}
				<button class="pkpButton pkpButton--isWarnable" onclick="return confirm({translate|json_encode|escape key="admin.confirmExpireSessions"})">{translate key="admin.expireSessions"}</button>
			</form>
		</template>
	</action-panel>
	<action-panel>
		<h2>{translate key="admin.deleteCache"}</h2>
		<p>
			{translate key="admin.deleteCache.description"}
		</p>
		<template slot="actions">
			<form type="post" action="{url op="clearDataCache"}">
				{csrf}
				<button class="pkpButton pkpButton--isWarnable">{translate key="admin.clearDataCache"}</button>
			</form>
			<form type="post" action="{url op="clearTemplateCache"}">
				{csrf}
				<button class="pkpButton pkpButton--isWarnable" onclick="return confirm({translate|json_encode|escape key="admin.confirmClearTemplateCache"})">{translate key="admin.clearTemplateCache"}</button>
			</form>
		</template>
	</action-panel>
	<action-panel>
		<h2>{translate key="admin.scheduledTask.clearLogs"}</h2>
		<p>
			{translate key="admin.scheduledTask.clearLogs.description"}
		</p>
		<template slot="actions">
			<form type="post" action="{url op="clearScheduledTaskLogFiles"}">
				{csrf}
				<button class="pkpButton pkpButton--isWarnable" onclick="return confirm({translate|json_encode|escape key="admin.scheduledTask.confirmClearLogs"})">{translate key="admin.scheduledTask.clearLogs.delete"}</button>
			</form>
		</template>
	</action-panel>
	<action-panel>
		<h2>{translate key="navigation.tools.jobs"}</h2>
		<p>
			{translate key="navigation.tools.jobs.description"}
		</p>
		<template slot="actions">
			<pkp-button
				element="a"
				href="{url op="jobs"}"
			>
				{translate key="navigation.tools.jobs.view"}
			</pkp-button>
		</template>
	</action-panel>
	{call_hook name="Templates::Admin::Index::AdminFunctions"}
{/block}
