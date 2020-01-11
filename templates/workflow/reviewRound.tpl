{**
 * templates/workflow/reviewRound.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Review round info for a particular round
 *}
<div class="pkp_panel_wrapper">

	{if $isLastReviewRound}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewRoundNotification_"|concat:$reviewRoundId requestOptions=$reviewRoundNotificationRequestOptions}
	{/if}

	{* Editorial decision actions, if available *}
	<div class="pkp_context_sidebar">
		{if !$isAssignedAsAuthor}
			{capture assign=reviewDecisionsUrl}{url router=$smarty.const.ROUTE_PAGE page="workflow" op="editorDecisionActions" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId contextId="reviewRoundTab-"|concat:$reviewRoundId escape=false}{/capture}
			{load_url_in_div id="reviewDecisionsDiv-"|concat:$reviewRoundId url=$reviewDecisionsUrl class="pkp_tab_actions" refreshOn="decisionActionUpdated"}
		{/if}
		{include file="controllers/tab/workflow/stageParticipants.tpl"}
	</div>

	<div class="pkp_content_panel">

		{* Review files grid *}
		{capture assign=reviewFileSelectionGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.EditorReviewFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="reviewFileSelection-round_"|concat:$reviewRoundId url=$reviewFileSelectionGridUrl}

		{* Reviewer grid *}
		{capture assign=reviewersGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.reviewer.ReviewerGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="reviewersGrid-round_"|concat:$reviewRoundId url=$reviewersGridUrl}

		{* Review revisions grid *}
		{capture assign=revisionsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.WorkflowReviewRevisionsGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="revisionsGrid-round_"|concat:$reviewRoundId url=$revisionsGridUrl}
	</div>
</div>
