{**
 * templates/stats/publications.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The publications statistics page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

	<div class="pkpStats">
		<pkp-header>
			<h1>{translate key="common.publications"}</h1>
			<spinner v-if="isLoadingTimeline"></spinner>
			<template slot="actions">
				<date-range
					unique-id="publication-stats-date-range"
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
				<pkp-header
					class="pkpStats__sidebarHeader"
					:tabindex="isSidebarVisible ? 0 : -1"
				>
					<h2>
						<icon icon="filter" :inline="true"></icon>
						{translate key="common.filter"}
					</h2>
				</pkp-header>
				<div
					v-for="(filterSet, index) in filters"
					:key="index"
					class="pkpStats__filterSet"
				>
					<pkp-header v-if="filterSet.heading">
						<h3>{{ filterSet.heading }}</h3>
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
				<div v-if="chartData" class="pkpStats__graph">
					<div class="pkpStats__graphHeader">
						<h2 class="pkpStats__graphTitle -screenReader" id="publication-stats-graph-title">
							{translate key="submission.views"}
						</h2>
						<div class="pkpStats__graphSelectors">
							<div class="pkpStats__graphSelector pkpStats__graphSelector--timelineType">
								<pkp-button
									:aria-pressed="timelineType === 'abstract'"
									aria-describedby="publication-stats-graph-title"
									@click="setTimelineType('abstract')"
								>
									{translate key="stats.publications.abstracts"}
								</pkp-button>
								<pkp-button
									:aria-pressed="timelineType === 'galley'"
									aria-describedby="publication-stats-graph-title"
									@click="setTimelineType('galley')"
								>
									{translate key="submission.files"}
								</pkp-button>
							</div>
							<div class="pkpStats__graphSelector pkpStats__graphSelector--timelineInterval">
								<pkp-button
									:aria-pressed="timelineInterval === 'day'"
									aria-describedby="publication-stats-graph-title"
									:disabled="!isDailyIntervalEnabled"
									@click="setTimelineInterval('day')"
								>
									{translate key="stats.daily"}
								</pkp-button>
								<pkp-button
									:aria-pressed="timelineInterval === 'month'"
									aria-describedby="publication-stats-graph-title"
									:disabled="!isMonthlyIntervalEnabled"
									@click="setTimelineInterval('month')"
								>
									{translate key="stats.monthly"}
								</pkp-button>
							</div>
						</div>
					</div>
					<table class="-screenReader" role="region" aria-live="polite">
						<caption v-if="timelineType === 'galley'">{translate key="stats.publications.totalGalleyViews.timelineInterval"}</caption>
						<caption v-else>{translate key="stats.publications.totalAbstractViews.timelineInterval"}</caption>
						<thead>
							<tr>
								<th scope="col">{translate key="common.date"}</th>
								<th v-if="timelineType === 'galley'" scope="col">{translate key="stats.fileViews"}</th>
								<th v-else scope="col">{translate key="submission.abstractViews"}</th>
							</tr>
						</thead>
						<tbody>
							<tr	v-for="segment in timeline" :key="segment.date">
								<th scope="row">{{ segment.label }}</th>
								<td>{{ segment.value }}</td>
							</tr>
						</tbody>
					</table>
					<line-chart :chart-data="chartData" aria-hidden="true"></line-chart>
					<span v-if="isLoadingTimeline" class="pkpStats__loadingCover">
						<spinner></spinner>
					</span>
				</div>
				<div class="pkpStats__panel" role="region" aria-live="polite">
					<pkp-header>
						<h2 id="publicationDetailTableLabel">
							{translate key="stats.publications.details"}
							<spinner v-if="isLoadingItems"></spinner>
						</h2>
						<template slot="actions">
							<div class="pkpStats__itemsOfTotal">
								{{
									replaceLocaleParams(itemsOfTotalLabel, {
										count: items.length,
										total: itemsMax
									})
								}}
								<a
									v-if="items.length < itemsMax"
									href="#publicationDetailTablePagination"
									class="-screenReader"
								>
									{translate key="common.pagination.label"}
								</a>
							</div>
						</template>
					</pkp-header>
					<pkp-table
						labelled-by="publicationDetailTableLabel"
						:class="tableClasses"
						:columns="tableColumns"
						:rows="items"
						:order-by="orderBy"
						:order-direction="orderDirection"
						@order-by="setOrderBy"
					>
						<search
							slot="thead-title"
							class="pkpStats__titleSearch"
							:search-phrase="searchPhrase"
							search-label="{translate key="stats.searchSubmissionDescription"}"
							@search-phrase-changed="setSearchPhrase"
						></search>
						<template slot-scope="{ row, rowIndex }">
							<table-cell
								v-for="(column, columnIndex) in tableColumns"
								:key="column.name"
								:column="column"
								:row="row"
								:tabindex="!rowIndex && !columnIndex ? 0 : -1"
							>
								<template v-if="column.name === 'title'">
									<a
										:href="row.publication.urlPublished"
										class="pkpStats__itemLink"
										target="_blank"
									>
										<span class="pkpStats__itemAuthors" v-html="row.publication.authorsStringShort"></span>
										<span class="pkpStats__itemTitle" v-html="localize(row.publication.fullTitle)"></span>
									</a>
								</template>
							</table-cell>
						</template>
					</pkp-table>
					<div v-if="!items.length" class="pkpStats__noRecords">
						<template v-if="isLoadingItems">
							<spinner></spinner>
							{translate key="common.loading"}
						</template>
						<template v-else>
							{translate key="stats.publications.none"}
						</template>
					</div>
					<pagination
						v-if="lastPage > 1"
						id="publicationDetailTablePagination"
						:current-page="currentPage"
						:is-loading="isLoadingItems"
						:last-page="lastPage"
						@set-page="setPage"
					></pagination>
				</div>
			</div>
		</div>
	</div>
{/block}
