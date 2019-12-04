{**
 * templates/stats/editorialReport.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The editorial statistics page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="editorial-stats-handler-{$uuid}" class="pkpStats pkpStats--editorial">
		<h1 class="-screenReader">{translate key="stats.editorialActivity"}</h1>
		<div v-if="activeByStage" class="pkpStats__graph">
			<div class="pkpStats--editorial__stageWrapper -pkpClearfix">
				<div class="pkpStats--editorial__stageChartWrapper">
					<doughnut-chart :chart-data="chartData"></doughnut-chart>
				</div>
				<div class="pkpStats--editorial__stageList">
					<h2 class="pkpStats--editorial__stage pkpStats--editorial__stage--total">
						<span class="pkpStats--editorial__stageCount">{{ totalActive }}</span>
						<span class="pkpStats--editorial__stageLabel">{translate key="stats.submissionsActive"}</span>
					</h2>
					<div v-for="stage in activeByStage" class="pkpStats--editorial__stage">
						<span class="pkpStats--editorial__stageCount">{{ stage.count }}</span>
						<span class="pkpStats--editorial__stageLabel">{{ stage.name }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="pkpStats__container -pkpClearfix">
			<!-- Filters in the sidebar -->
			<div
				v-if="filters.length"
				ref="sidebar"
				class="pkpStats__sidebar"
				:class="sidebarClasses"
			>
				<pkp-header
					class="pkpStats__sidebarHeader"
					:tabindex="isSidebarVisible ? 0 : -1"
				>
					<icon icon="filter" :inline="true"></icon>
					{{ i18n.filter }}
				</pkp-header>
				<div
					v-for="(filterSet, index) in filters"
					:key="index"
					class="pkpStats__filterSet"
				>
					<pkp-header v-if="filterSet.heading">
						{{ filterSet.heading }}
					</pkp-header>
					<pkp-filter
						v-for="filter in filterSet.filters"
						:key="filter.param + filter.value"
						v-bind="filter"
						:is-filter-active="isFilterActive(filter.param, filter.value)"
						:i18n="i18n"
						@add-filter="addFilter"
						@remove-filter="removeFilter"
					></pkp-filter>
				</div>
			</div>
			<div class="pkpStats__content">
				<div class="pkpStats__table" role="region" aria-live="polite">
				<div class="pkpStats__tableHeader">
					<h2 class="pkpStats__tableTitle" id="editorialActivityTabelLabel">
						{translate key="stats.trends"}
						<span v-if="isLoadingItems" class="pkpSpinner" aria-hidden="true"></span>
					</h2>
					<div class="pkpStats__tableActions">
						<date-range
							slot="thead-dateRange"
							unique-id="editorial-stats-date-range"
							:date-start="dateStart"
							:date-end="dateEnd"
							:date-end-max="dateEndMax"
							:options="dateRangeOptions"
							:i18n="i18n"
							@set-range="setDateRange"
							@updated:current-range="setCurrentDateRange"
						></date-range>
						<pkp-button
							v-if="filters.length"
							:label="i18n.filter"
							icon="filter"
							:is-active="isSidebarVisible"
							@click="toggleSidebar"
						></pkp-button>
					</div>
				</div>
					<pkp-table
						class="pkpTable--editorialStats"
						labelled-by="editorialActivityTabelLabel"
						:columns="tableColumns"
						:rows="tableRows"
					>
						<template slot-scope="{ldelim}row, rowIndex{rdelim}">
							<table-cell
								v-for="(column, columnIndex) in tableColumns"
								:key="column.name"
								:column="column"
								:row="row"
								:tabindex="!rowIndex && !columnIndex ? 0 : -1"
							>
								<template v-if="column.name === 'name'">
									{{ row.name }}
									<tooltip v-if="row.description"
										:label="__('descriptionForStat', {ldelim}stat: row.name{rdelim})"
										:tooltip="row.description"
									></tooltip>
								</template>
							</table-cell>
						</template>
					</pkp-table>
				</div>
			</div>
		</div>
	</div>
	<script>
		pkp.registry.init('editorial-stats-handler-{$uuid}', 'StatsEditorialContainer', {$statsComponent->getConfig()|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
