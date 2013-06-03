{**
 * templates/authorDashboard/submissionDocuments.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the submission documents part of the author dashboard.
 *}
{if array_key_exists($smarty.const.WORKFLOW_STAGE_ID_SUBMISSION, $accessibleWorkflowStages)}
	<div class="pkp_authorDashboard_stageContainer" id="documents">
		<h3><a href="#">{translate key='submission.documents'}</a></h3>
		<div id="documentsContent">
			{url|assign:documentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
			{load_url_in_div id="documentsGridDiv" url=$documentsGridUrl}
		</div>
	</div>
{/if}
