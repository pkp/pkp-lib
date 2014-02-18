{**
 * templates/authorDashboard/stages/production.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the production stage on the author dashboard.
 *}
{if array_key_exists($smarty.const.WORKFLOW_STAGE_ID_PRODUCTION, $accessibleWorkflowStages)}
	<div class="pkp_authorDashboard_stageContainer" id="production">
		<h3><a href="#">{translate key='submission.production'}</a></h3>
		<div id="productionContent">
			{if $stageId >= $smarty.const.WORKFLOW_STAGE_ID_PRODUCTION}
				{include file="authorDashboard/submissionEmails.tpl" submissionEmails=$productionEmails}

				<!-- Display production files grid -->
				{url|assign:productionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.proof.AuthorProofingSignoffFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING escape=false}
				{load_url_in_div id="productionFilesGridDiv" url=$productionFilesGridUrl}
			{else}
				{translate key="submission.stageNotInitiated"}
			{/if}
		</div>
	</div>
{/if}
