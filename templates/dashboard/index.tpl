{**
 * templates/dashboard/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Dashboard index.
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
					:is-primary="true"
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
					<table-header
						:can-sort="true"
						:sort-direction="sortColumn === 'id' ? sortDirection : 'none'"
						@table:sort="sort('id')"
					>
						{translate key="common.id"}
					</table-header>
					<table-header>
						{translate key="navigation.submissions"}
					</table-header>
					<table-header>
						{translate key="workflow.stage"}
					</table-header>
					<table-header>
						{translate key="editor.submission.days"}
					</table-header>
					<table-header>
						{translate key="stats.editorialActivity"}
					</table-header>
					<table-header>
						<span class="-screenReader">
							{translate key="admin.jobs.list.actions"}
						</span>
					</table-header>
				</template>
				<template v-for="submission in submissions">
					<tr :key="submission.id">
						<td is="table-cell">{{ submission.id }}</td>
						<td is="table-cell"
							class="submissions__list__item__title"
							:id="'submission-title-' + submission.id"
							:is-row-header="true"
						>
							<strong>
								{{ submission.publications[0].authorsStringShort }}
							</strong>
							<template v-if="submission.publications[0].authorsStringShort">—</template>
							{{ submission.publications[0].fullTitle.en }}
						</td>
						<td is="table-cell" class="submissions__list__item__stage">
							<stage-bubble :stage-id="submission.stageId">
								{{ submission.stageName }}
								<template v-if="
									(
										submission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_INTERNAL_REVIEW}
										|| submission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW}
									)
									&& submission.reviewRounds.length
								">
									(Round {{ submission.reviewRounds[submission.reviewRounds.length - 1].round }})
								</template>
							</stage-bubble>
						</td>
						<td is="table-cell">
							TODO
						</td>
						<td is="table-cell">
							TODO
						</td>
						<td is="table-cell">
							<pkp-button
								class="submissions__list__item__view"
								:aria-describedby="'submission-title-' + submission.id"
								:is-link="true"
								@click="openSummary(submission)"
							>
								{translate key="editor.submission.viewSummary"}
							</pkp-button>
						</td>
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
			<template slot="header">
				<stage-bubble :stage-id="summarySubmission.stageId">
					{{ summarySubmission.stageName }}
					<template v-if="summarySubmission.reviewRound">
						(Round {{ summarySubmission.reviewRound }})
					</template>
				</stage-bubble>
				<span class="summary__id">
					{{ summarySubmission.id }}
				</span>
			</template>
			<h2 class="summary__authors">
				{{ summarySubmission.publications[0].authorsStringShort }}
			</h2>
			<div class="summary__title">
				{{ summarySubmission.publications[0].fullTitle.en }}
			</div>
			<panel>
				<panel-section>
					<p>{translate key="editor.submission.daysInStage"}: XX</p>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
				</panel-section>
			</panel>
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
