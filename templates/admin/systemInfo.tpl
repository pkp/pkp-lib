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

		<h2 id="versionHistory" class="mt-5">{translate key="admin.versionHistory"}</h2>

		<pkp-table labelled-by="versionHistory">
			<pkp-table-header>
				<pkp-table-column>{translate key="admin.version"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.versionMajor"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.versionMinor"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.versionRevision"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.versionBuild"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.dateInstalled"}</pkp-table-column>
			</pkp-table-header>
			<pkp-table-body>
				{foreach from=$versionHistory item="version"}
					<pkp-table-row>
						<pkp-table-cell>{$version->getVersionString(false)}</pkp-table-cell>
						<pkp-table-cell>{$version->getMajor()}</pkp-table-cell>
						<pkp-table-cell>{$version->getMinor()}</pkp-table-cell>
						<pkp-table-cell>{$version->getRevision()}</pkp-table-cell>
						<pkp-table-cell>{$version->getBuild()}</pkp-table-cell>
						<pkp-table-cell>{$version->getDateInstalled()|date_format:$dateFormatShort}</pkp-table-cell>
					</pkp-table-row>
				{/foreach}
			</pkp-table-body>
		</pkp-table>

		<h2 id="serverInformation" class="mt-5">{translate key="admin.serverInformation"}</h2>

		<pkp-table labelled-by="serverInformation">
			<pkp-table-header>
				<pkp-table-column>{translate key="admin.systemInfo.settingName"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.systemInfo.settingValue"}</pkp-table-column>
			</pkp-table-header>
			<pkp-table-body>
				{foreach from=$serverInfo item="value" key="name"}
					<pkp-table-row>
						<pkp-table-cell>{translate key=$name}</pkp-table-cell>
						<pkp-table-cell>{$value|escape}</pkp-table-cell>
					</pkp-table-row>
				{/foreach}
			</pkp-table-body>
		</pkp-table>

		<h2 id="systemConfiguration{$key}" class="mt-5">{translate key="admin.systemConfiguration"}</h2>

		<pkp-table labelled-by="systemConfiguration{$key}">
			<pkp-table-header>
				<pkp-table-column>{translate key="admin.systemInfo.settingName"}</pkp-table-column>
				<pkp-table-column>{translate key="admin.systemInfo.settingValue"}</pkp-table-column>
			</pkp-table-header>
			{foreach from=$configData item="settings" key="category"}
				<pkp-table-body>
					<pkp-table-row>
						<pkp-table-cell colspan="2" class="app--admin__systemInfoGroup">{$category}</pkp-table-cell>
					</pkp-table-row>
					{foreach from=$settings item="value" key="name"}
						<pkp-table-row>
							<pkp-table-cell>{$name|escape}</pkp-table-cell>
							{if \PKP\config\Config::isSensitive($category, $name)}
								<pkp-table-cell>**************</pkp-table-cell>
							{else}
								<pkp-table-cell>{$value|escape}</pkp-table-cell>
							{/if}
						</pkp-table-row>
					{/foreach}
				</pkp-table-body>
			{/foreach}
		</pkp-table>


		<a href="{url op="phpinfo"}" target="_blank">{translate key="admin.phpInfo"}</a>
	</div><!-- .pkp_page_content -->
{/block}
