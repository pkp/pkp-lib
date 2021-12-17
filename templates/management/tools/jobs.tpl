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
            <tabs label="{translate key="navigation.tools.jobs"}">
                <tab label="{translate key="navigation.tools.queuedjobs"}" id="showQueuedJobs" badge="{{$totalQueuedJobs}}">
                    <jobs-table
                        :table_data=components.queuedJobsTable
                    />
                </tab>
            </tabs>
        </div>
    </div>
{/block}
