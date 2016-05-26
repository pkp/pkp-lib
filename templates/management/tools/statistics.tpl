{**
 * templates/management/tools/statistics.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the statistics & reporting page.
 *
 *}
{include file="common/header.tpl" pageTitle="manager.statistics"}

<div class="pkp_page_content pkp_page_statistics">
	{help file="tools.md" section="statistics"}

	{if $showMetricTypeSelector || $appSettings}
		{include file="management/tools/form/statisticsSettingsForm.tpl"}
	{/if}

	<h3>{translate key="manager.statistics.reports"}</h3>
	<p>{translate key="manager.statistics.reports.description"}</p>

	<ul>
	{foreach from=$reportPlugins key=key item=plugin}
		<li><a href="{url op="tools" path="report" pluginName=$plugin->getName()|escape}">{$plugin->getDisplayName()|escape}</a></li>
	{/foreach}
	</ul>

	<p><a class="pkp_button" href="{url op="tools" path="reportGenerator"}">{translate key="manager.statistics.reports.generateReport"}</a></p>
</div>
{include file="common/footer.tpl"}
