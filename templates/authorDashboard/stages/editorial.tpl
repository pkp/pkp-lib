{**
 * templates/authorDashboard/stages/editorial.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the editorial stage on the author dashboard.
 *}
{if array_key_exists($smarty.const.WORKFLOW_STAGE_ID_EDITING, $accessibleWorkflowStages)}
	<div class="pkp_authorDashboard_stageContainer" id="copyediting">
		<h3><a href="#">{translate key='submission.copyediting'}</a></h3>
		<div id="copyeditingContent">
			{if $stageId >= $smarty.const.WORKFLOW_STAGE_ID_EDITING}
				<!-- Display editor's message to the author -->
				{include file="authorDashboard/submissionEmails.tpl" submissionEmails=$copyeditingEmails}

				<!-- Display queries grid -->
				{url|assign:queriesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING escape=false}
				{load_url_in_div id="queriesGridDiv" url=$queriesGridUrl}
			{else}
				{translate key="submission.stageNotInitiated"}
			{/if}
		</div>
	</div>
{/if}
