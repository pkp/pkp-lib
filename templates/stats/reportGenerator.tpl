{**
 * templates/stats/reportGenerator.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Report generator page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.statistics.reports.customReportGenerator"}
	</h1>

	<div class="app__contentPanel">
			{capture assign=reportGeneratorUrl}{url router=$smarty.const.ROUTE_COMPONENT component="statistics.ReportGeneratorHandler" op="fetchReportGenerator" escape=false}{/capture}
			{load_url_in_div id="reportGeneratorContainer" url="$reportGeneratorUrl"}
	</div>
{/block}
