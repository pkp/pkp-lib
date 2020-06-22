{**
 * templates/management/statistics/reportGenerator.tpl
 *
 * Copyright (c) 2013-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Report generator page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.statistics.reports"}
	</h1>

	<div class="pkp_page_content pkp_page_statistics">
			{capture assign=reportGeneratorUrl}{url router=$smarty.const.ROUTE_COMPONENT component="statistics.ReportGeneratorHandler" op="fetchReportGenerator" escape=false}{/capture}
			{load_url_in_div id="reportGeneratorContainer" url="$reportGeneratorUrl"}
	</div>
{/block}
