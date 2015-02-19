{**
 * templates/authorDashboard/stages/submission.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission stage of the author dashboard.
 *}
{if array_key_exists($smarty.const.WORKFLOW_STAGE_ID_SUBMISSION, $accessibleWorkflowStages)}
	<div class="pkp_authorDashboard_stageContainer" id="submission">
		<h3><a href="#">{translate key='submission.submission'}</a></h3>
		<div id="submissionContent">
			{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.AuthorSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
			{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}
		</div>
	</div>
{/if}
