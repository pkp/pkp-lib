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
		<pkp-table
			:columns="columns"
			:rows="rows"
			:label="label"
		>
			<template slot-scope="{ row, rowIndex }">
                <table-cell
                    v-for="(column, columnIndex) in columns"
                    :key="column.name"
                    :column="column"
                    :row="row"
                    :tabindex="!rowIndex && !columnIndex ? 0 : -1"
                >
                    <span v-if="column.name === 'value' && isValidJson(row.value)">
						<pre>{{ row.value }}</pre>
					</span>
                </table-cell>
            </template>
		</pkp-table>
	</div>
{/block}
