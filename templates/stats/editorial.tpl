{**
 * lib/pkp/templates/stats/editorial.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The editorial statistics page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

	<div class="pkpStats pkpStats--editorial">
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
					<div v-for="stage in activeByStage" :key="stage.name" class="pkpStats--editorial__stage">
						<span class="pkpStats--editorial__stageCount">{{ stage.count }}</span>
						<span class="pkpStats--editorial__stageLabel">{{ stage.name }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="pkpStats__panel">
			<pkp-header>
				<h1 id="editorialActivityTabelLabel">
					{translate key="stats.trends"}
					<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
				</h1>
				<template slot="actions">
					<date-range
						slot="thead-dateRange"
						unique-id="editorial-stats-date-range"
						:date-start="dateStart"
						:date-end="dateEnd"
						:date-end-max="dateEndMax"
						:options="dateRangeOptions"
						date-range-label="{translate key="stats.dateRange"}"
						date-format-instructions-label="{translate key="stats.dateRange.instructions"}"
						change-date-range-label="{translate key="stats.dateRange.change"}"
						since-date-label="{translate key="stats.dateRange.sinceDate"}"
						until-date-label="{translate key="stats.dateRange.untilDate"}"
						all-dates-label="{translate key="stats.dateRange.allDates"}"
						custom-range-label="{translate key="stats.dateRange.customRange"}"
						from-date-label="{translate key="stats.dateRange.from"}"
						to-date-label="{translate key="stats.dateRange.to"}"
						apply-label="{translate key="stats.dateRange.apply"}"
						invalid-date-label="{translate key="stats.dateRange.invalidDate"}"
						date-does-not-exist-label="{translate key="stats.dateRange.dateDoesNotExist"}"
						invalid-date-range-label="{translate key="stats.dateRange.invalidDateRange"}"
						invalid-end-date-max-label="{translate key="stats.dateRange.invalidEndDateMax"}"
						invalid-start-date-min-label="{translate key="stats.dateRange.invalidStartDateMin"}"
						@set-range="setDateRange"
						@updated:current-range="setCurrentDateRange"
					></date-range>
					<pkp-button
						v-if="filters.length"
						:is-active="isSidebarVisible"
						@click="toggleSidebar"
					>
						<icon icon="filter" :inline="true"></icon>
						{translate key="common.filter"}
					</pkp-button>
				</template>
			</pkp-header>
			<div class="pkpStats__container -pkpClearfix">
				<!-- Filters in the sidebar -->
				<div
					v-if="filters.length"
					ref="sidebar"
					class="pkpStats__sidebar"
					:class="sidebarClasses"
				>
					<div
						v-for="(filterSet, index) in filters"
						:key="index"
						class="pkpStats__filterSet"
					>
						<pkp-header v-if="filterSet.heading">
							<h2>{{ filterSet.heading }}</h2>
						</pkp-header>
						<pkp-filter
							v-for="filter in filterSet.filters"
							:key="filter.param + filter.value"
							v-bind="filter"
							:is-filter-active="isFilterActive(filter.param, filter.value)"
							@add-filter="addFilter"
							@remove-filter="removeFilter"
						></pkp-filter>
					</div>
				</div>
				<div class="pkpStats__content">
					<div class="pkpStats__table" role="region" aria-live="polite">
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
											:label="__('stats.descriptionForStat', {ldelim}stat: row.name{rdelim})"
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
	</div>
{/block}
