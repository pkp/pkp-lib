{**
 * templates/controllers/modals/editorDecision/form/promoteForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

	<div id="promoteForm-step1">
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

		<div id="sendReviews-emailContent" style="margin-bottom: 30px;">
			{* Message to author textarea *}
			{fbvFormSection for="personalMessage"}
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

		{if $decisionData.paymentType}
			{fbvFormSection title="common.payment"}
				<ul class="checkbox_and_radiobutton">
					{fbvElement type="radio" id="requestPayment-request" name="requestPayment" value="1" checked=$requestPayment|compare:1 label=$decisionData.requestPaymentText translate=false}
					{fbvElement type="radio" id="requestPayment-waive" name="requestPayment" value="0" checked=$requestPayment|compare:0 label=$decisionData.waivePaymentText translate=false}
				</ul>
			{/fbvFormSection}
		{/if}

		{** Some decisions can be made before review is initiated (i.e. no attachments). **}
		{if $reviewRoundId}
			<div id="attachments">
				{capture assign=reviewAttachmentsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorSelectableReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
				{load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
			</div>
		{/if}

		<div id="libraryFileAttachments" class="pkp_user_group_other_contexts">
			{capture assign=libraryAttachmentsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.SelectableLibraryFileGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
			{capture assign=libraryAttachmentsGrid}{load_url_in_div id="libraryFilesAttachmentsGridContainer" url=$libraryAttachmentsGridUrl}{/capture}
			{include file="controllers/extrasOnDemand.tpl"
				id="libraryFileAttachmentsExtras"
				widgetWrapper="#libraryFileAttachments"
				moreDetailsText="settings.libraryFiles.public.selectLibraryFiles"
				lessDetailsText="settings.libraryFiles.public.selectLibraryFiles"
				extraContent=$libraryAttachmentsGrid
			}
		</div>
	</div>

	<div id="promoteForm-step2">
		{capture assign="stageName"}{translate key=$decisionData.toStage}{/capture}
		<p>{translate key="editor.submission.decision.selectFiles" stageName=$stageName}</p>
		{* Show a different grid depending on whether we're in review or before the review stage *}
		{if $stageId == $smarty.const.WORKFLOW_STAGE_ID_SUBMISSION}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SelectableSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
		{elseif $reviewRoundId}
			{** a set $reviewRoundId var implies we are INTERNAL_REVIEW or EXTERNAL_REVIEW **}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.SelectableReviewRevisionsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{elseif $stageId == $smarty.const.WORKFLOW_STAGE_ID_EDITING}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.SelectableCopyeditFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
			{capture assign=draftFilesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.final.SelectableFinalDraftFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
			{load_url_in_div id="draftFilesToPromoteGridUrl" url=$draftFilesToPromoteGridUrl}
		{/if}
		{load_url_in_div id="filesToPromoteGrid" url=$filesToPromoteGridUrl}
	</div>

	{fbvFormSection class="formButtons form_buttons"}
		<button class="pkp_button promoteForm-step-btn" data-step="files">
			{translate key="editor.submission.decision.nextButton" stageName=$stageName}
		</button>
		{fbvElement type="submit" class="submitFormButton pkp_button_primary" id="promoteForm-complete-btn" label="editor.submissionReview.recordDecision"}
		<button class="pkp_button promoteForm-step-btn" data-step="email">
			{translate key="editor.submission.decision.previousAuthorNotification"}
		</button>
		{assign var=cancelButtonId value="cancelFormButton"|concat:"-"|uniqid}
		<a href="#" id="{$cancelButtonId}" class="cancelButton">{translate key="common.cancel"}</a>
		<span class="pkp_spinner"></span>
	{/fbvFormSection}
</form>
