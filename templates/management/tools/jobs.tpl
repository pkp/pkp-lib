{**
 * templates/management/tools/jobs.tpl
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
		{translate key="navigation.tools.jobs"}
	</h1>
	<div class="app__contentPanel">
        <pkp-table
            :columns="columns"
            :rows="rows"
            :description="compiledDescription"
            :label="label"
        />
    </div>
{/block}
