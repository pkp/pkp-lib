{**
 * templates/workflow/submission.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission details (metadata, file grid)
 *}
 {include file="controllers/tab/workflow/stageParticipants.tpl"}

<ul class="pkp_context_panel">
	{url|assign:submissionEditorDecisionsUrl router=$smarty.const.ROUTE_PAGE page="workflow" op="editorDecisionActions" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}
	{load_url_in_el el="li" id="submissionEditorDecisionsDiv" url=$submissionEditorDecisionsUrl class="pkp_context_actions"}
</ul>

<div class="pkp_content_panel">
	<p class="pkp_help">{translate key="editor.submission.introduction"}</p>

	{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.EditorSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
	{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

	{url|assign:documentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
	{load_url_in_div id="documentsGridDiv" url=$documentsGridUrl}
</div>
