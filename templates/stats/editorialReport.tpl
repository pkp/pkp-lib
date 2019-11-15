{**
 * templates/stats/editorialReport.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The editorial report page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="editorial-report-handler-{$uuid}" class="pkpStatistics pkpStatistics--editorial">
		<h1 class="-screenReader">{translate key="manager.statistics.editorial.editorialActivity"}</h1>
		<div v-if="submissionsStage" class="pkpStatistics__graph">
			<div class="pkpStatistics--editorial__stageWrapper -pkpClearfix">
				<div class="pkpStatistics--editorial__stageChartWrapper">
					<doughnut-chart :chart-data="editorialChartData"></doughnut-chart>
				</div>
				<div class="pkpStatistics--editorial__stageList">
					<h2 class="pkpStatistics--editorial__stage pkpStatistics--editorial__stage--total">
						<span class="pkpStatistics--editorial__stageCount">{{ submissionsStage.reduce((a, b) => a + b.value, 0 ) }}</span>
						<span class="pkpStatistics--editorial__stageLabel">{translate key="manager.statistics.editorial.submissionsActive"}</span>
					</h2>
					<div v-for="stage in submissionsStage" class="pkpStatistics--editorial__stage">
						<span class="pkpStatistics--editorial__stageCount">{{ stage.value }}</span>
						<span class="pkpStatistics--editorial__stageLabel">{{ stage.name }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="pkpStatistics__container">
			<list-panel-filter
				v-if="hasFilters"
				:is-visible="isFilterVisible"
				:filters="filters"
				:active-filters="activeFilters"
				:i18n="i18n"
				@filter-list="updateFilter"
			></list-panel-filter>
			<div class="pkpStatistics__main">
				<div class="pkpStatistics__table" role="region" aria-live="polite">
				<div class="pkpStatistics__tableHeader">
					<h2 class="pkpStatistics__tableTitle" id="editorialActivityTabelLabel">
						{translate key="manager.statistics.editorial.trends"}
						<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
					</h2>
					<div class="pkpStatistics__tableActions">
						<date-range
							unique-id="editorial-stats-date-range"
							:date-start="dateStart"
							:date-end="dateEnd"
							:date-end-max="dateEndMax"
							:options="dateRangeOptions"
							:i18n="i18n"
							@set-range="setDateRange"
						></date-range>
						<pkp-button
							:label="i18n.filter"
							icon="filter"
							:is-active="isFilterVisible"
							@click="toggleFilter"
						></pkp-button>
					</div>
				</div>
					<pkp-table
						labelled-by="editorialActivityTabelLabel"
						:columns="tableColumns"
						:rows="editorialItems"
					></pkp-table>
				</div>
				<div class="pkpStatistics__table" role="region" aria-live="polite">
					<div class="pkpStatistics__tableHeader">
						<h3 class="pkpStatistics__tableTitle" id="usersDetailTableLabel">
							{translate key="manager.statistics.users.newUserSignups"}
							<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
						</h3>
					</div>
					<pkp-table
						labelled-by="usersDetailTableLabel"
						:columns="tableColumns"
						:rows="userItems"
					></pkp-table>
				</div>
			</div>
		</div>
	</div>
	<script>
		pkp.registry.init('editorial-report-handler-{$uuid}', 'StatisticsEditorial', {$statsComponent->getConfig()|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
