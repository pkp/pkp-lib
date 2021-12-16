{**
 * templates/management/tools/jobs.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Jobs index
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
        {translate key="navigation.tools.jobs"}
    </h1>

    <tabs label="Jobs Operations">
        <tab label="Queued Jobs" id="showQueuedJobs">
            {literal}
            <list-panel v-bind=components.queuedJobsItems>
                <template v-slot:itemActions="{item}">
                    <pkp-button isWarnable="true">
                        Delete
                    </pkp-button>
                </template>
            </list-panel>
            {/literal}
            {* <pkp-table v-bind=components.queuedJobsTable /> *}
        </tab>
        <tab label="Failed Jobs" id="showFailedJobs">
            Lorem Ipsum
        </tab>
    </tabs>
{/block}
