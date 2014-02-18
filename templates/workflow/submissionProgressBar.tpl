{**
 * templates/workflow/submissionProgressBar.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the submission progress bar
 *}
{assign var=primaryAuthor value=$submission->getPrimaryAuthor()}
{if !$primaryAuthor}
	{assign var=authors value=$submission->getAuthors()}
	{assign var=primaryAuthor value=$authors[0]}
{/if}
{assign var="pageTitleTranslated" value=$primaryAuthor->getLastName()|concat:", <em>":$submission->getLocalizedTitle():"</em>"|truncate:50}
<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#submissionHeader').pkpHandler(
			'$.pkp.pages.workflow.SubmissionHeaderHandler'
		);
	{rdelim});
</script>
<div id="submissionHeader" class="pkp_page_header">
	<div class="participant_popover" style="display: none;">
		{url|assign:stageParticipantGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.stageParticipant.StageParticipantGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
		{load_url_in_div id="stageParticipantGridContainer" url=$stageParticipantGridUrl}
	</div>
	<div class="pkp_helpers_align_right">
		<ul class="submission_actions pkp_helpers_flatlist">
			{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $userRoles)}
				<li>{include file="linkAction/linkAction.tpl" action=$submissionEntryAction}</li>
			{/if}
			<li>{include file="linkAction/linkAction.tpl" action=$submissionInformationCenterAction}</li>
			<li class="participants"><a href="javascript:$.noop();" id="participantToggle" class="sprite participants">{translate key="editor.submission.stageParticipants"}</a></li>
		</ul>
	</div>
	<div class="pkp_helpers_align_left"><span class="h2">{$pageTitleTranslated}</span></div>

	<div class="submission_progress_wrapper">
		<ul class="submission_progress pkp_helpers_flatlist">
			{foreach key=key from=$workflowStages item=stage}
				{assign var="progressClass" value=""}
				{assign var="currentClass" value=""}
				{translate|assign:"stageTitle" key="submission.noActionRequired"}
				{if $stageNotifications[$key]}
					{assign var="progressClass" value="actionNeeded"}
					{translate|assign:"stageTitle" key="submission.actionNeeded"}
				{/if}
				{if !array_key_exists($key, $accessibleWorkflowStages)}
					{assign var="progressClass" value="stageDisabled"}
				{/if}
				{if $submissionIsReady && $stage.path == $smarty.const.WORKFLOW_STAGE_PATH_PRODUCTION}
					{assign var="progressClass" value="productionReady"}
					{translate|assign:"stageTitle" key="submission.productionReady"}
				{/if}
				{if $key == $stageId}
					{assign var="currentClass" value="current"}
					{translate|assign:"stageTitle" key="submission.currentStage"}
				{/if}
				<li class="{$progressClass} {$currentClass}">
					{if array_key_exists($key, $accessibleWorkflowStages)}
						<a href="{url router=$smarty.const.ROUTE_PAGE page="workflow" op=$stage.path path=$submission->getId()}" title="{$stageTitle}">{translate key=$stage.translationKey}</a>
					{else}
						<a class="pkp_common_disabled">{translate key=$stage.translationKey}</a>
					{/if}
				</li>
			{/foreach}
		</ul>
	</div>

</div>
