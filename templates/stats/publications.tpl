{**
 * templates/stats/publications.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The publications statistics page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="publication-stats-handler-{$uuid}" class="pkpStats">
		<pkp-header>
			{translate key="common.publications"}
			<spinner v-if="isLoadingTimeline"></spinner>
			<template slot="actions">
				<date-range
					unique-id="publication-stats-date-range"
					:date-start="dateStart"
					:date-end="dateEnd"
					:date-end-max="dateEndMax"
					:options="dateRangeOptions"
					:i18n="i18n"
					@set-range="setDateRange"
				></date-range>
				<pkp-button
					v-if="filters.length"
					icon="filter"
					:label="i18n.filter"
					:is-active="isSidebarVisible"
					@click="toggleSidebar"
				></pkp-button>
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
				<div v-if="chartData" class="pkpStats__graph">
					<div class="pkpStats__graphHeader">
						<h2 class="pkpStats__graphTitle" id="publication-stats-time-segment">
							{translate key="submission.abstractViews"}
						</h2>
						<div class="pkpStats__graphSegment">
							<pkp-button
								:label="i18n.daily"
								:aria-pressed="timelineInterval === 'day'"
								aria-describedby="publication-stats-time-segment"
								:disabled="!isDailySegmentEnabled"
								@click="setTimelineInterval('day')"
							></pkp-button>
							<pkp-button
								:label="i18n.monthly"
								:aria-pressed="timelineInterval === 'month'"
								aria-describedby="publication-stats-time-segment"
								:disabled="!isMonthlySegmentEnabled"
								@click="setTimelineInterval('month')"
							></pkp-button>
						</div>
					</div>
					<table class="-screenReader" role="region" aria-live="polite">
						<caption>{translate key="stats.publications.totalAbstractViews.timelineInterval"}</caption>
						<thead>
							<tr>
								<th scope="col">{translate key="common.date"}</th>
								<th scope="col">{translate key="submission.abstractViews"}</th>
							</tr>
						</thead>
						<tbody>
							<tr	v-for="segment in timeline" :key="segment.date">
								<th scope="row">{{ segment.label }}</th>
								<th>{{ segment.value }}</th>
							</tr>
						</tbody>
					</table>
					<line-chart :chart-data="chartData" aria-hidden="true"></line-chart>
				</div>
				<div class="pkpStats__table" role="region" aria-live="polite">
					<div class="pkpStats__tableHeader">
						<h2 class="pkpStats__tableTitle" id="publicationDetailTableLabel">
							{translate key="stats.publications.details"}
							<spinner v-if="isLoadingItems"></spinner>
						</h2>
						<div class="pkpStats__tableActions">
							<div class="pkpStats__itemsOfTotal">
								{{ __('itemsOfTotal', { count: items.length, total: itemsMax }) }}
								<a
									v-if="items.length < itemsMax"
									href="#publicationDetailTablePagination"
									class="-screenReader"
								>
									{{ i18n.paginationLabel }}
								</a>
							</div>
						</div>
					</div>
					<pkp-table
						labelled-by="publicationDetailTableLabel"
						:columns="tableColumns"
						:rows="items"
					>
						<search
							slot="thead-title"
							class="pkpStats__titleSearch"
							:search-phrase="searchPhrase"
							:search-label="i18n.search"
							:clear-search-label="i18n.clearSearch"
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
										<span class="pkpStats__itemAuthors" v-html="row.publication.shortAuthorString"></span>
										<span class="pkpStats__itemTitle" v-html="localize(row.publication.fullTitle)"></span>
									</a>
								</template>
							</table-cell>
						</template>
					</pkp-table>
					<pagination
						v-if="lastPage > 1"
						id="publicationDetailTablePagination"
						:current-page="currentPage"
						:last-page="lastPage"
						:i18n="i18n"
						@set-page="setPage"
					></pagination>
					<div v-if="!items.length" class="pkpStats__noRecords">
						<template v-if="isLoadingItems">
							<spinner></spinner>
							{translate key="common.loading"}
						</template>
						<template v-else>
							{translate key="stats.publications.none"}
						</template>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		pkp.registry.init('publication-stats-handler-{$uuid}', 'StatsContainer', {$statsComponent->getConfig()|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
