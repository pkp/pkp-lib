{**
 * templates/admin/jobs.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Jobs index
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key=$pageTitle}
	</h1>
	<div class="app__contentPanel">
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
					<table-cell>{{ row.attempts }}</table-cell>
					<table-cell>{{ row.created_at }}</table-cell>
				</table-row>
			</table-body>
		</pkp-table>

		<pagination v-if="lastPage > 1"
			:current-page="currentPage"
			:last-page="lastPage"
			:is-loading="isLoadingItems"
			@set-page="handlePagination"
		/>
	</div>
{/block}
