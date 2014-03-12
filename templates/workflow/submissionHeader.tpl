{**
 * templates/workflow/submissionHeader.tpl
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
			'$.pkp.pages.workflow.SubmissionHeaderHandler', {ldelim}
				participantToggleSeletor: '#participantToggle'
			{rdelim}
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

	{url|assign:submissionProgressBarUrl op="submissionProgressBar" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}
	{load_url_in_div id="submissionProgressBarDiv" url=$submissionProgressBarUrl class="submissionProgressBar"}

</div>
