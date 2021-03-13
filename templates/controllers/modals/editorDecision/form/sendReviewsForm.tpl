{**
 * templates/controllers/modals/editorDecision/form/sendReviewsForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Form used to send reviews to author
 *
 * @uses $revisionsEmail string Email body for requesting revisions that don't
 *  require another round of review.
 * @uses $resubmitEmail string Email body for asking the author to resubmit for
 *  another round of review.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#sendReviews').pkpHandler(
			'$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler',
			{ldelim}
				{if $revisionsEmail}
					revisionsEmail: {$revisionsEmail|json_encode},
				{/if}
				{if $resubmitEmail}
					resubmitEmail: {$resubmitEmail|json_encode},
				{/if}
				peerReviewUrl: {$peerReviewUrl|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="sendReviews" method="post" action="{url op=$saveFormOperation}" >
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

	{* Set the decision or allow the decision to be selected *}
	{if $decision != $smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS && $decision != $smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT}
		<input type="hidden" name="decision" value="{$decision|escape}" />
	{else}
		{if $decision == $smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS}
			{assign var="checkedRevisions" value="1"}
		{elseif $decision == $smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT}
			{assign var="checkedResubmit" value="1"}
		{/if}
		{fbvFormSection title="editor.review.newReviewRound"}
			<ul class="checkbox_and_radiobutton">
				{fbvElement type="radio" id="decisionRevisions" name="decision" value=$smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS checked=$checkedRevisions label="editor.review.NotifyAuthorRevisions"}
				{fbvElement type="radio" id="decisionResubmit" name="decision" value=$smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT checked=$checkedResubmit label="editor.review.NotifyAuthorResubmit"}
			</ul>
		{/fbvFormSection}
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

		{if isset($reviewers)}
			{include file="controllers/modals/editorDecision/form/bccReviewers.tpl"
				reviewers=$reviewers
				selected=$bccReviewers
			}
		{/if}
	</div>

	{** Some decisions can be made before review is initiated (i.e. no attachments). **}
	{if $reviewRoundId}
		<div id="attachments" style="margin-top: 30px;">
			{capture assign=reviewAttachmentsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorSelectableReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
			{load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
		</div>
	{/if}

	{fbvFormButtons submitText="editor.submissionReview.recordDecision"}
</form>
