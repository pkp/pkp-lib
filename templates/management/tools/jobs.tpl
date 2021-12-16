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
    <div class="pkpStats">
        <div class="pkpStats__panel">
            <pkp-header>
                <h1 id="jobsList" class="pkpHeader__title">
                    {translate key="navigation.tools.jobs"}
                </h1>
            </pkp-header>
            <tabs label="Jobs Operations">
                <tab label="Queued Jobs" id="showQueuedJobs">
                    {*
                    {literal}
                    <list-panel v-bind=components.queuedJobsItems>
                        <template slot="itemActions">
                            <pkp-button ref="deleteQueuedJob" @click="$modal.show('export')">
                                Delete
                            </pkp-button>
                        </template>
                    </list-panel>
                    {/literal}
                    *}
                    {*<pkp-table v-bind=components.queuedJobsTable />*}
                    <table class="pkpTable" labelled-by="usersTableLabel">
                        <thead>
                            <tr>
                                <th>{translate key="common.name"}</th>
                                <th>{translate key="stats.total"}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>ID</td>
                                <td>Name</td>
                            </tr>
                        </tbody>
                    </table>
                </tab>
                <tab label="Failed Jobs" id="showFailedJobs">
                    Lorem Ipsum
                </tab>
            </tabs>
        </div>
    </div>
    {*<modal
        v-bind="MODAL_PROPS"
        name="deleteQueuedJob"
        @closed="setFocusToRef('deleteQueuedJob')"
    >
        <modal-content
            close-label="common.close"
            modal-name="deleteQueuedJob"
            title="Delete Queued Job"
        >
            Delete this job?
        </modal-content>
    </modal>*}
{/block}
