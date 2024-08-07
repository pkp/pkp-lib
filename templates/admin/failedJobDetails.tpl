{**
 * templates/admin/failedJobDetails.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Failed Jobs Details
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key=$pageTitle}
	</h1>
	<div class="app__contentPanel app__contentPanel__overflow">
		<div class="bg-default p-4">
			<caption v-if="label">
				<div class="pkpTable__label">
					<slot name="label">{{ label }}</slot>
				</div>
			</caption>
		</div>
		<pkp-table :aria-label="label">
			<table-header>
				<table-column v-for="column in columns" :key="column.name" :id="column.name">
					{{ column.label }}
				</table-column>
			</table-header>
			<table-body>
				<table-row v-for="(row) in rows" :key="row.key">
					<table-cell v-html="row.attribute"></span>
					</table-cell>
					<table-cell>
						<pre>{{ row.value }}</pre>
					</table-cell>
				</table-row>
			</table-body>
		</pkp-table>
	</div>
{/block}
