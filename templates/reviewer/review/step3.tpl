{**
 * templates/reviewer/review/step3.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Show the step 3 review page
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#reviewStep3Form').pkpHandler(
			'$.pkp.controllers.form.reviewer.ReviewerReviewStep3FormHandler'
		);
	{rdelim});
</script>

<form class="pkp_form" id="reviewStep3Form" method="post" action="{url op="saveStep" path=$submission->getId() step="3"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewStep3FormNotification"}

{fbvFormArea id="reviewStep3"}

	{capture assign="reviewFilesGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.ReviewerReviewFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false}{/capture}
	{load_url_in_div id="reviewFilesStep3" url=$reviewFilesGridUrl}

	{if $viewGuidelinesAction}
		{fbvFormSection title="reviewer.submission.reviewerGuidelines"}
			<div id="viewGuidelines">
				{include file="linkAction/linkAction.tpl" action=$viewGuidelinesAction contextId="viewGuidelines"}
			</div>
		{/fbvFormSection}
	{/if}

	{fbvFormSection label="submission.review" description="reviewer.submission.reviewDescription"}
		{if $reviewForm}
			{include file="reviewer/review/reviewFormResponse.tpl"}
		{else}
			{fbvFormSection}
				{fbvElement type="textarea" id="comments" name="comments" value=$comments readonly=$reviewIsComplete label="submission.comments.canShareWithAuthor" rich=true}
			{/fbvFormSection}
			{fbvFormSection}
				{fbvElement type="textarea" id="commentsPrivate" name="commentsPrivate" value=$commentsPrivate readonly=$reviewIsComplete label="submission.comments.cannotShareWithAuthor" rich=true}
			{/fbvFormSection}
		{/if}
	{/fbvFormSection}

	{fbvFormSection label="common.upload" description="reviewer.submission.uploadDescription"}
		{capture assign="reviewAttachmentsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.ReviewerReviewAttachmentsGridHandler" op="fetchGrid" assocType=$smarty.const.ASSOC_TYPE_REVIEW_ASSIGNMENT assocId=$submission->getReviewId() submissionId=$submission->getId() stageId=$submission->getStageId() reviewIsComplete=$reviewIsComplete escape=false}{/capture}
		{load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
	{/fbvFormSection}

	<!-- Display queries grid -->
	{capture assign="queriesGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW escape=false}{/capture}
	{load_url_in_div id="queriesGrid" url=$queriesGridUrl}	

	{$additionalFormFields}	

	{capture assign="cancelUrl"}{url page="reviewer" op="submission" path=$submission->getId() step=2 escape=false}{/capture}
	{fbvFormButtons submitText="reviewer.submission.submitReview" confirmSubmit="reviewer.confirmSubmit" cancelText="navigation.goBack" cancelUrl=$cancelUrl cancelUrlTarget="_self" submitDisabled=$reviewIsComplete}
{/fbvFormArea}

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
