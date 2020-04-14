{**
 * templates/stats/publications.tpl
 *
 * Copyright (c) 2013-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The publications statistics page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true pageTitle="stats.publicationStats"}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="publication-stats-handler-{$uuid}" class="pkpStats">
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
					dateRangeLabel="{translate key="stats.dateRange"}"
					dateFormatInstructionsLabel="{translate key="stats.dateRange.instructions"}"
					changeDateRangeLabel="{translate key="stats.dateRange.change"}"
					sinceDateLabel="{translate key="stats.dateRange.sinceDate"}"
					untilDateLabel="{translate key="stats.dateRange.untilDate"}"
					allDatesLabel="{translate key="stats.dateRange.allDates"}"
					customRangeLabel="{translate key="stats.dateRange.customRange"}"
					fromDateLabel="{translate key="stats.dateRange.from"}"
					toDateLabel="{translate key="stats.dateRange.to"}"
					applyLabel="{translate key="stats.dateRange.apply"}"
					invalidDateLabel="{translate key="stats.dateRange.invalidDate"}"
					dateDoesNotExistLabel="{translate key="stats.dateRange.dateDoesNotExist"}"
					invalidDateRangeLabel="{translate key="stats.dateRange.invalidDateRange"}"
					invalidEndDateMaxLabel="{translate key="stats.dateRange.invalidEndDateMax"}"
					invalidStartDateMinLabel="{translate key="stats.dateRange.invalidStartDateMin"}"
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
						:i18n="i18n"
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
				<div class="pkpStats__table" role="region" aria-live="polite">
					<div class="pkpStats__tableHeader">
						<h2 class="pkpStats__tableTitle" id="publicationDetailTableLabel">
							{translate key="stats.publications.details"}
							<spinner v-if="isLoadingItems"></spinner>
						</h2>
						<div class="pkpStats__tableActions">
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
						</div>
					</div>
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
						:i18n="i18n"
						@set-page="setPage"
					></pagination>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		pkp.registry.init('publication-stats-handler-{$uuid}', 'StatsPublicationsContainer', {$statsComponent->getConfig()|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
