{**
 * templates/controllers/modals/editorDecision/form/promoteForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form used to send reviews to author
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		$('#promote').pkpHandler(
			'$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler',
			{ldelim}
				peerReviewUrl: {$peerReviewUrl|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="promote" method="post" action="{url op=$saveFormOperation}" >
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="decision" value="{$decision|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

	{if array_key_exists('help', $decisionData)}
		<p>{translate key=$decisionData.help}</p>
	{/if}

	{capture assign="sendEmailLabel"}{translate key="editor.submissionReview.sendEmail" authorName=$authorName}{/capture}
	{if $skipEmail}
		{assign var="skipEmailSkip" value=true}
	{else}
		{assign var="skipEmailSend" value=true}
	{/if}
	{fbvFormSection title="common.sendEmail"}
		<ul class="checkbox_and_radiobutton">
			{fbvElement type="radio" id="skipEmail-send" name="skipEmail" value="0" checked=$skipEmailSend label=$sendEmailLabel translate=false}
			{fbvElement type="radio" id="skipEmail-skip" name="skipEmail" value="1" checked=$skipEmailSkip label="editor.submissionReview.skipEmail"}
		</ul>
	{/fbvFormSection}

	<div id="sendReviews-emailContent">
		{* Message to author textarea *}
		{fbvFormSection title="editor.review.personalMessageToAuthor" for="personalMessage"}
			{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true variables=$allowedVariables variablesType=$allowedVariablesType}
		{/fbvFormSection}

		{* Button to add reviews to the email automatically *}
		{if $reviewsAvailable}
			{fbvFormSection}
				<a id="importPeerReviews" href="#" class="pkp_button">
					<span class="fa fa-plus" aria-hidden="true"></span>
					{translate key="submission.comments.addReviews"}
				</a>
			{/fbvFormSection}
		{/if}
	</div>

	{** Some decisions can be made before review is initiated (i.e. no attachments). **}
	{if $reviewRoundId}
		<div id="attachments" style="margin-top: 30px;">
			{url|assign:reviewAttachmentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorSelectableReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}
			{load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
		</div>
	{/if}

	<div id="availableFiles" style="margin-top: 30px;">
		{* Show a different grid depending on whether we're in review or before the review stage *}
		{if $stageId == $smarty.const.WORKFLOW_STAGE_ID_SUBMISSION}
			{url|assign:filesToPromoteGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SelectableSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}
		{elseif $reviewRoundId}
			{** a set $reviewRoundId var implies we are INTERNAL_REVIEW or EXTERNAL_REVIEW **}
			{url|assign:filesToPromoteGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.SelectableReviewRevisionsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}
		{elseif $stageId == $smarty.const.WORKFLOW_STAGE_ID_EDITING}
			{url|assign:filesToPromoteGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.SelectableCopyeditFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}
		{/if}
		{load_url_in_div id="filesToPromoteGrid" url=$filesToPromoteGridUrl}
	</div>
	{fbvFormButtons submitText="editor.submissionReview.recordDecision"}
</form>
