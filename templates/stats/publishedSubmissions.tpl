{**
 * templates/stats/articles.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The article statistics page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="article-stats-handler-{$uuid}" class="pkpStatistics">
		<page-header>
			{translate key="common.publishedSubmissions"}
			<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
			<template slot="actions">
				<date-range
					unique-id="article-stats-date-range"
					:date-start="dateStart"
					:date-end="dateEnd"
					:date-end-max="dateEndMax"
					:options="dateRangeOptions"
					:i18n="i18n"
					@set-range="setDateRange"
				></date-range>
				<pkp-button
					v-if="hasFilters"
					:label="i18n.filter"
					icon="filter"
					:is-active="isFilterVisible"
					@click="toggleFilter"
				></pkp-button>
			</template>
		</page-header>
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
				<div v-if="chartData" class="pkpStatistics__graph">
					<div class="pkpStatistics__graphHeader">
						<h2 class="pkpStatistics__graphTitle" id="article-stats-time-segment">Abstract Views</h2>
						<div class="pkpStatistics__graphSegment">
							<pkp-button
								:label="i18n.day"
								:aria-pressed="timeSegment === 'day'"
								aria-describedby="article-stats-time-segment"
								:disabled="!isDailySegmentEnabled"
								@click="setTimeSegment('day')"
							></pkp-button>
							<pkp-button
								:label="i18n.month"
								:aria-pressed="timeSegment === 'month'"
								aria-describedby="article-stats-time-segment"
								:disabled="!isMonthlySegmentEnabled"
								@click="setTimeSegment('month')"
							></pkp-button>
						</div>
					</div>
					<table class="-screenReader" role="region" aria-live="polite">
						<caption>{translate key="stats.publishedSubmissions.totalAbstractViews.timeSegment"}</caption>
						<thead>
							<tr>
								<th scope="col">{translate key="common.date"}</th>
								<th scope="col">{translate key="submission.abstractViews"}</th>
								<th scope="col">{translate key="stats.fileViews"}</th>
								<th scope="col">{translate key="stats.total"}</th>
							</tr>
						</thead>
						<tbody>
							<tr	v-for="segment in timeSegments">
								<th scope="row">{{ segment.dateLabel }}</th>
								<th>{{ segment.abstractViews }}</th>
								<th>{{ segment.totalFileViews }}</th>
								<th>{{ segment.total }}</th>
							</tr>
						</tbody>
					</table>
					<line-chart :chart-data="chartData" aria-hidden="true"></line-chart>
				</div>
				<div class="pkpStatistics__table" role="region" aria-live="polite">
					<div class="pkpStatistics__tableHeader">
						<h2 class="pkpStatistics__tableTitle" id="articleDetailTableLabel">
							{translate key="stats.publishedSubmissions.details"}
							<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
						</h2>
						<div class="pkpStatistics__tableActions">
							<div class="pkpStatistics__itemsOfTotal">
								{{ __('itemsOfTotal', { count: items.length, total: itemsMax }) }}
								<a
									v-if="items.length < itemsMax"
									href="#articleDetailTablePagination"
									class="-screenReader"
								>
									{{ i18n.paginationLabel }}
								</a>
							</div>
						</div>
					</div>
					<pkp-table
						labelled-by="articleDetailTableLabel"
						:columns="tableColumns"
						:rows="items"
						:order-by="orderBy"
						:order-direction="orderDirection"
						@order-by="setOrderBy"
					>
						<list-panel-search
							slot="thead-title"
							slot-scope="{ column }"
							:search-phrase="searchPhrase"
							:i18n="i18n"
							@search-phrase-changed="setSearchPhrase"
						></list-panel-search>
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
										:href="row.object.urlPublished"
										class="pkpStatistics__itemLink"
										target="_blank"
									>
										<span class="pkpStatistics__itemAuthors" v-html="row.object.shortAuthorString"></span>
										<span class="pkpStatistics__itemTitle" v-html="localize(row.object.fullTitle)"></span>
									</a>
								</template>
							</table-cell>
						</template>
					</pkp-table>
					<pagination
						v-if="lastPage > 1"
						id="articleDetailTablePagination"
						:current-page="currentPage"
						:last-page="lastPage"
						:i18n="i18n"
						@set-page="setPage"
					></pagination>
					<div v-if="!items.length" class="pkpStatistics__noRecords">
						{translate key="stats.publishedSubmissions.none"}
					</div>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		pkp.registry.init('article-stats-handler-{$uuid}', 'StatisticsSubmissions', {$statsComponent->getConfig()|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
