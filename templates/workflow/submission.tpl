{**
 * templates/workflow/submission.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission details (metadata, file grid)
 *}

{url|assign:submissionEditorDecisionsUrl router=$smarty.const.ROUTE_PAGE page="workflow" op="editorDecisionActions" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}
{load_url_in_div id="submissionEditorDecisionsDiv" url=$submissionEditorDecisionsUrl class="editorDecisionActions"}

<p class="pkp_help">{translate key="editor.submission.introduction"}</p>

{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.EditorSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

{url|assign:documentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}
{load_url_in_div id="documentsGridDiv" url=$documentsGridUrl}
</div>

