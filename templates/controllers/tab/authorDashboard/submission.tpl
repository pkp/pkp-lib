{**
 * lib/pkp/templates/controllers/tab/authorDashboard/submission.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission stage of the author dashboard.
 *}
{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.AuthorSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

<div id="documentsContent">
	{url|assign:documentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
	{load_url_in_div id="documentsGridDiv" url=$documentsGridUrl}
</div>
