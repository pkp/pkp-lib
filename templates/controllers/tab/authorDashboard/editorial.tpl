{**
 * lib/pkp/templates/controllers/tab/authorDashboard/editorial.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display the editorial stage on the author dashboard.
 *}
{if $submission->getStageId() >= $smarty.const.WORKFLOW_STAGE_ID_EDITING}
	<!-- Display editor's message to the author -->
	{include file="authorDashboard/submissionEmails.tpl" submissionEmails=$copyeditingEmails}

	<!-- Display queries grid -->
	{capture assign=queriesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING escape=false}{/capture}
	{load_url_in_div id="queriesGrid" url=$queriesGridUrl}

	<!-- Copyedited Files grid -->
	{if $canAccessCopyeditingStage}
		{capture assign=copyeditedFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.CopyeditFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
		{load_url_in_div id="copyeditedFilesGrid" url=$copyeditedFilesGridUrl}
	{/if}
{else}
	{translate key="submission.stageNotInitiated"}
{/if}
