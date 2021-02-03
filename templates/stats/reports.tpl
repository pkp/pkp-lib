{**
 * lib/pkp/templates/stats/reports.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The editorial statistics page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.statistics.reports"}
	</h1>
    <div class="app__contentPanel">
        <p>{translate key="manager.statistics.reports.description"}</p>

        <ul>
        {foreach from=$reportPlugins key=key item=plugin}
            <li><a href="{url op="reports" path="report" pluginName=$plugin->getName()|escape}">{$plugin->getDisplayName()|escape}</a></li>
        {/foreach}
        </ul>

        <p><a class="pkp_button" href="{url op="reports" path="reportGenerator"}">{translate key="manager.statistics.reports.generateReport"}</a></p>
    </div>
{/block}
