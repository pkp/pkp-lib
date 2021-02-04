{**
 * systemInfo.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display system information.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="admin.systemInformation"}
	</h1>

	{if $newVersionAvailable}
		<notification>
			{translate key="site.upgradeAvailable.admin" currentVersion=$currentVersion->getVersionString(false) latestVersion=$latestVersion}
		</notification>
	{/if}

	<div class="app__contentPanel">

		<h2>{translate key="admin.currentVersion"}: {$currentVersion->getVersionString(false)} ({$currentVersion->getDateInstalled()|date_format:$datetimeFormatLong})</h2>

		{if $latestVersionInfo}
				<p>{translate key="admin.version.latest"}: {$latestVersionInfo.release|escape} ({$latestVersionInfo.date|date_format:$dateFormatLong})</p>
			{if $currentVersion->compare($latestVersionInfo.version) < 0}
				<p><strong>{translate key="admin.version.updateAvailable"}</strong>: <a href="{$latestVersionInfo.package|escape}">{translate key="admin.version.downloadPackage"}</a> | {if $latestVersionInfo.patch}<a href="{$latestVersionInfo.patch|escape}">{translate key="admin.version.downloadPatch"}</a>{else}{translate key="admin.version.downloadPatch"}{/if} | <a href="{$latestVersionInfo.info|escape}">{translate key="admin.version.moreInfo"}</a></p>
			{else}
				<p><strong>{translate key="admin.version.upToDate"}</strong></p>
			{/if}
		{else}
		<p><a href="{url versionCheck=1}">{translate key="admin.version.checkForUpdates"}</a></p>
		{/if}

		<h2 id="versionHistory">{translate key="admin.versionHistory"}</h2>

		<table class="pkpTable" aria-labelledby="versionHistory">
			<thead>
				<tr>
					<th>{translate key="admin.version"}</th>
					<th>{translate key="admin.versionMajor"}</th>
					<th>{translate key="admin.versionMinor"}</th>
					<th>{translate key="admin.versionRevision"}</th>
					<th>{translate key="admin.versionBuild"}</th>
					<th>{translate key="admin.dateInstalled"}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$versionHistory item="version"}
					<tr>
						<td>{$version->getVersionString(false)}</td>
						<td>{$version->getMajor()}</td>
						<td>{$version->getMinor()}</td>
						<td>{$version->getRevision()}</td>
						<td>{$version->getBuild()}</td>
						<td>{$version->getDateInstalled()|date_format:$dateFormatShort}</td>
					</tr>
				{/foreach}
			</tbody>
		</table>

		<h2 id="serverInformation">{translate key="admin.serverInformation"}</h2>

		<table class="pkpTable" aria-labelledby="serverInformation">
			<thead>
				<tr>
					<th>{translate key="admin.systemInfo.settingName"}</th>
					<th>{translate key="admin.systemInfo.settingValue"}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$serverInfo item="value" key="name"}
					<tr>
						<td>{translate key=$name}</td>
						<td>{$value|escape}</td>
					</tr>
				{/foreach}
			</tbody>
		</table>

		<h2>{translate key="admin.systemConfiguration"}</h2>

		<table class="pkpTable" aria-labelledby="systemConfiguration{$key}">
			<thead>
				<tr>
					<th>{translate key="admin.systemInfo.settingName"}</th>
					<th>{translate key="admin.systemInfo.settingValue"}</th>
				</tr>
			</thead>
			{foreach from=$configData item="settings" key="category"}
				<tbody>
					<tr>
						<td colspan="2" class="app--admin__systemInfoGroup">{$category}</td>
					</tr>
					{foreach from=$settings item="value" key="name"}
						<tr>
							<td>{$name|escape}</td>
							<td>{$value|escape}</td>
						</tr>
					{/foreach}
				</tbody>
			{/foreach}
		</table>


		<a href="{url op="phpinfo"}" target="_blank">{translate key="admin.phpInfo"}</a>
	</div><!-- .pkp_page_content -->
{/block}
