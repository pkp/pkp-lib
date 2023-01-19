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
		<pkp-table
			:columns="columns"
			:rows="rows"
			:description="description"
			:label="label"
            :api-url="apiUrl"
		>
            <template slot="actions" slot-scope="slotProps">
                <td>
                    <div class="buttonRow">
                        <pkp-button @click="this.pkp.eventBus.$emit('redispatch-job', slotProps.row)">
                            {translate key="admin.jobs.failed.action.redispatch"}
                        </pkp-button>
                        <pkp-button is-warnable @click="this.pkp.eventBus.$emit('remove-job', slotProps.row)">
                            {translate key="admin.jobs.failed.action.delete"}
                        </pkp-button>
                        <pkp-button element="a" is-link :href="slotProps.row.detailsPath">
                            {translate key="admin.jobs.failed.action.details"}
                        </pkp-button>
                    </div>
                </td>
            </template>
		</pkp-table>

		<pagination
			:current-page="currentPage"
			:last-page="lastPage"
			:is-loading="isLoadingItems"
			@set-page="handlePagination"
		/>
	</div>
{/block}
