{**
 * templates/dashboard/editors.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Dashboard homepage showing submissions for editorial staff.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<div class="submissions">
		<div class="submissions__views">
			<h1 class="submissions__views__title">
				{translate key="navigation.submissions"}
			</h1>
			<ul class="submissions__views__list">
				<li v-for="view in views" :key="view.id" class="submissions__view">
					<button
						class="submissions__view__button"
						:class="
							currentView.id === view.id
								? 'submissions__view__button--current'
								: ''
						"
						@click="loadView(view)"
					>
						<span class="submissions__view__count">
							{{ view.count }}
						</span>
						<span class="submissions__view__name">
							{{ view.name }}
						</span>
					</button>
				</li>
			</ul>
		</div>
		<div class="submissions__list">
			<div class="submissions__list__top">
				<pkp-button
					element="a"
					href="{url page="submission"}"
				>
					{translate key="manager.newSubmission"}
				</pkp-button>
			</div>
			<h2 class="submissions__list__title" id="table-title">
				{{ currentView.name }}
				<span class="submissions__view__count">
					{{ submissionsMax }}
				</span>
			</h2>
			<div id="table-controls" class="submissions__list__controls">
				<button-row>
					<template slot="end">
						<pkp-button @click="openFilters" tabindex="0">
							{translate key="common.filter"}
						</pkp-button>
						<span v-if="isLoadingSubmissions">
							<spinner></spinner>
							{translate key="common.loading"}
						</span>
					</template>
					<search
						:search-phrase="searchPhrase"
						search-label="{translate key="editor.submission.search"}"
						@search-phrase-changed="setSearchPhrase"
					></search>
				</button-row>
			</div>
			<pkp-table aria-labelledby="table-title" aria-describedby="table-controls">
				<template slot="head">
					{foreach from=$columns item="column"}
						<table-header
						{if $column->sortable}
							:can-sort="true"
							:sort-direction="sortColumn === '{$column->id}' ? sortDirection : 'none'"
							@table:sort="sort('{$column->id}')"
						{/if}
						>
							{$column->header}
						</table-header>
					{/foreach}
				</template>
				<template v-for="submission in submissions">
					<tr :key="submission.id">
						{foreach from=$columns item="column"}
							{include file=$column->template}
						{/foreach}
					</tr>
				</template>
			</pkp-table>
			<div class="submissions__list__footer">
				<span class="submission__list__showing" v-html="showingXofX"></span>
				<pagination
					v-if="lastPage > 1"
					slot="footer"
					:current-page="currentPage"
					:is-loading="isLoadingPage"
					:last-page="lastPage"
					:show-adjacent-pages="3"
					@set-page="setPage"
				></pagination>
			</div>
		</div>
	</div>
	<modal
		close-label="Close"
		name="summary"
		type="side"
		@closed="resetFocusToList"
	>
		<template v-if="summarySubmission">
			{include file="dashboard/summary.tpl"}
		</template>
	</modal>
	<modal
		close-label="Close"
		name="filters"
		type="side"
		@closed="resetFocusToList"
	>
		<template slot="header">
			<h2>
				{translate key="common.filter"}
			</h2>
		</template>
		<panel>
			<panel-section>
				<pkp-form
					v-bind="filtersForm"
					@set="setFiltersForm"
					@success="saveFilters"
				></pkp-form>
			</panel-section>
		</panel>
	</modal>
{/block}
