{**
 * lib/pkp/templates/controllers/tab/authorDashboard/submission.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Submission stage of the author dashboard.
 *}
{capture assign=submissionFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.AuthorSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}{/capture}
{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

<div id="documentsContent">
	<!-- Display queries grid -->
	{capture assign=queriesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_SUBMISSION escape=false}{/capture}
	{load_url_in_div id="queriesGrid" url=$queriesGridUrl}
</div>
