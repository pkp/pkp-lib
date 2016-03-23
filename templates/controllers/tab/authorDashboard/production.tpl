{**
 * lib/pkp/templates/controllers/tab/authorDashboard/production.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the production stage on the author dashboard.
 *}
{if $submission->getStageId() >= $smarty.const.WORKFLOW_STAGE_ID_PRODUCTION}
	{include file="authorDashboard/submissionEmails.tpl" submissionEmails=$productionEmails}

	<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#representationTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
	</script>

	<div id="representationTabs" class="pkp_controllers_tab">
		<ul>
			{foreach from=$representations item=representation}
				<li>
					<a href="#representation-{$representation->getId()|escape}">{$representation->getLocalizedName()|escape}</a>
				</li>
			{/foreach}
		</ul>
		{foreach from=$representations item=representation}
			<div id="representation-{$representation->getId()|escape}">
				{url|assign:queriesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
				{load_url_in_div id="queriesGrid-"|concat:$representation->getId() url=$queriesGridUrl}
			</div>
		{/foreach}
	</div>
{else}
	{translate key="submission.stageNotInitiated"}
{/if}
