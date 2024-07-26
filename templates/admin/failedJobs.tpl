{**
 * templates/admin/failedJobs.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Failed Jobs index
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key=$pageTitle}
	</h1>
	<div class="app__contentPanel">
		<div class="bg-default p-4" v-if="total > 0">
			<caption v-if="label || description">
				<div v-if="label" class="pkpTable__label">
					<div>
						<span class="pkp_helpers_half pkp_helpers_align_left">
							{{ label }}
						</span>
						<span
							class="pkp_helpers_half pkp_helpers_align_right pkp_helpers_text_right"
						>
							<spinner v-if="isLoadingItems"></spinner>
							<pkp-button @click="requeueAll">
								{translate key="admin.jobs.failed.action.redispatch.all"}
							</pkp-button>
						</span>
					</div>
				</div>
				<div
					v-if="description"
					class="pkpTable__description"
					v-html="description"
				></div>
			</caption>
		</div>
		<pkp-table :aria-label="label">
			<table-header>
				<table-column
					v-for="column in columns"
					:key="column.name"
					:id="column.name"
				>
					{{ column.label }}
				</table-column>
			</table-header>
			<table-body>
				<table-row v-for="(row) in rows" :key="row.key">
					<table-cell>{{ row.id }}</table-cell>
					<table-cell>{{ row.displayName }}</table-cell>
					<table-cell>{{ row.queue }}</table-cell>
					<table-cell>{{ row.connection }}</table-cell>
					<table-cell>{{ row.failed_at }}</table-cell>
					<table-cell>
						<button-row>
							<pkp-button @click="redispatch(row)">
								{translate key="admin.jobs.failed.action.redispatch"}
							</pkp-button>
							<pkp-button is-warnable @click="remove(row)">
								{translate key="common.delete"}
							</pkp-button>
							<pkp-button element="a" is-link :href="row._hrefs._details">
								{translate key="common.details"}
							</pkp-button>
						</button-row>
					</table-cell>
				</table-row>
			</table-body>
		</pkp-table>

		<pagination 
			v-if="lastPage > 1"
			:current-page="currentPage"
			:last-page="lastPage"
			:is-loading="isLoadingItems"
			@set-page="handlePagination"
		/>
	</div>
{/block}
