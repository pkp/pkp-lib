{**
 * templates/workflow/editorial.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Editorial workflow stage
 *}
<div id="editorial">

	{* Help Link *}
	{help file="chapter5/copyediting.md" class="pkp_help_tab"}

	<div class="pkp_context_sidebar">
		{url|assign:copyeditingEditorDecisionsUrl router=$smarty.const.ROUTE_PAGE page="workflow" op="editorDecisionActions" submissionId=$submission->getId() stageId=$stageId contextId="copyediting" escape=false}
		{load_url_in_div id="copyeditingEditorDecisionsDiv" url=$copyeditingEditorDecisionsUrl class="editorDecisionActions pkp_tab_actions"}
		{include file="controllers/tab/workflow/stageParticipants.tpl"}
	</div>

	<div class="pkp_content_panel">
		{url|assign:finalDraftFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.final.FinalDraftFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
		{load_url_in_div id="finalDraftFilesGrid" url=$finalDraftFilesGridUrl}

		{url|assign:queriesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
		{load_url_in_div id="queriesGrid" url=$queriesGridUrl}

		{url|assign:copyeditedFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.CopyeditFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
		{load_url_in_div id="copyeditedFilesGrid" url=$copyeditedFilesGridUrl}
	</div>

</div>
