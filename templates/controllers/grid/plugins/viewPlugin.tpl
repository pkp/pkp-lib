{**
 * controllers/grid/plugins/viewPlugin.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * View a plugin gallery plugin's details.
 *}
<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#viewPluginTabs').pkpHandler('$.pkp.controllers.TabHandler', {ldelim}
				notScrollable: true
		{rdelim});
	{rdelim});
</script>

<div id="viewPluginTabs">
	<ul>
		<li><a href="#pluginOverviewContainer">{translate key="manager.plugins.pluginGallery.overview"}</a></li>
		<li><a href="#pluginDescriptionContainer">{translate key="common.description"}</a></li>
	</ul>
	<div id="pluginOverviewContainer">
		<div class="pluginReleaseDetails">
			<h4>{translate key="manager.plugins.pluginGallery.latestCompatible"}</h4>
			<div class="pluginVersion">{translate key="manager.plugins.pluginGallery.version" version=$plugin->getVersion()|escape}</div>
			<div class="pluginDate">{translate key="manager.plugins.pluginGallery.date" date=$plugin->getDate()|date_format:$dateFormatShort}</div>
			<ul class="pluginCertifications">
				{foreach from=$plugin->getReleaseCertifications() item=certification}
					<li class="{$certification}" title="{translate key="manager.plugins.pluginGallery.certifications.$certification"}" />
				{/foreach}
			</ul>
			<div id="releaseDescription" class="pkp_helpers_clear">
				{$plugin->getLocalizedReleaseDescription()|strip_unsafe_html}
			</div>
		</div>
		<div id="pluginMaintainer">
			<h4>{translate key="manager.plugins.pluginGallery.maintainer"}</h4>
			<div id="maintainerContact">
				{if $plugin->getContactEmail()}<a href="mailto:{$plugin->getContactEmail()|escape}">{/if}{$plugin->getContactName()|escape}{if $plugin->getContactEmail()}</a>{/if}<br/>
			</div>
			<div id="maintainerInstitution">{$plugin->getContactInstitutionName()|escape}</div>
		</div>

		<div id="pluginHomepage">
			<h4>{translate key="manager.plugins.pluginGallery.homepage"}</h4>
			<a href="{$plugin->getHomepage()|escape}" target="_blank">{$plugin->getHomepage()|escape}</a>
		</div>

		<div id="pluginSummary">
			<h4>{translate key="manager.plugins.pluginGallery.summary"}</h4>
			{$plugin->getLocalizedSummary()|escape}
		</div>

		<div id="pluginStatus">
			<h4>{translate key="common.status"}</h4>

			{if $installedVersion}
				{if $installedVersion->compare($plugin->getVersion())>0}
					{* Already installed and newer than available *}
					<div class="pluginStatus newer">{translate key="manager.plugins.installedVersionNewer"}</div>
				{elseif $installedVersion->compare($plugin->getVersion())<0}
					{* Already installed but old *}
					<div class="pluginStatus older">{translate key="manager.plugins.installedVersionOlder"}</div>
				{else}
					{* Already installed and newest *}
					<div class="pluginStatus newest">{translate key="manager.plugins.installedVersionNewest"}</div>
				{/if}
			{else}
				<div class="pluginStatus notinstalled">{translate key="manager.plugins.noInstalledVersion"}</div>
			{/if}
		</div>

	</div>
	<div id="pluginDescriptionContainer">
		<div class="pluginDescription">{$plugin->getLocalizedDescription()|strip_unsafe_html}</div>
	</div>
</div>
