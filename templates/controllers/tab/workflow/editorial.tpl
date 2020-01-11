{**
 * templates/workflow/editorial.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Editorial workflow stage
 *}
<div id="editorial">

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="editingNotification_"|concat:$submission->getId() requestOptions=$editingNotificationRequestOptions refreshOn="stageStatusUpdated"}

	{* Help Link *}
	{help file="editorial-workflow/copyediting" class="pkp_help_tab"}

	<div class="pkp_context_sidebar">
		{capture assign=copyeditingEditorDecisionsUrl}{url router=$smarty.const.ROUTE_PAGE page="workflow" op="editorDecisionActions" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
		{load_url_in_div id="copyeditingEditorDecisionsDiv" url=$copyeditingEditorDecisionsUrl class="editorDecisionActions pkp_tab_actions"}
		{include file="controllers/tab/workflow/stageParticipants.tpl"}
	</div>

	<div class="pkp_content_panel">
		{capture assign=finalDraftFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.final.FinalDraftFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
		{load_url_in_div id="finalDraftFilesGrid" url=$finalDraftFilesGridUrl}

		{capture assign=queriesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
		{load_url_in_div id="queriesGrid" url=$queriesGridUrl}

		{capture assign=copyeditedFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.CopyeditFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
		{load_url_in_div id="copyeditedFilesGrid" url=$copyeditedFilesGridUrl}
	</div>

</div>
