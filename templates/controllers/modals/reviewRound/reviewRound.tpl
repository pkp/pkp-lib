{**
 * templates/controllers/modals/reviewRound/reviewRound.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display reviewer review round info modal.
 *}

<script type="text/javascript">
    // Attach the JS file tab handler.
    $(function() {ldelim}
        $('#historyTab').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
</script>
<script type="text/javascript">
    $(function() {ldelim}
        // Attach the form handler.
        $('#okButtonForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
</script>
<div id="historyTab">
	<ul>
		<li><a name="history" href="#historyDiv">{translate key="reviewer.submission.reviewRound.info.history"}</a></li>
	</ul>
	<div id="historyDiv">
		{if $reviewAssignment->getDeclined() == false}
			<h4 style="margin-top: 0;">{translate key="reviewer.article.recommendation"}:</h4>
			<div>
				<p>{$reviewAssignment->getLocalizedRecommendation()}</p>
			</div>

			{if !$reviewComments->wasEmpty()}
				<h4>{translate key="reviewer.submission.comments.review"}:</h4>
				{iterate from=reviewComments item=reviewComment}
					<div>
						{if $reviewComment->getViewable() == 1}
							<b>{translate key="reviewer.submission.comments.authorAndEditor"}:</b>
						{else}
							<b>{translate key="reviewer.submission.comments.editorOnly"}:</b>
						{/if}
						{$reviewComment->getComments()}
					</div>
				{/iterate}
			{/if}

			{if $displayFilesGrid}
				{capture assign="reviewAttachmentsModalUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.ReviewerReviewAttachmentsGridHandler" op="fetchGrid" assocType=$smarty.const.ASSOC_TYPE_REVIEW_ASSIGNMENT assocId=$reviewAssignment->getId() submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewIsClosed=true escape=false}{/capture}
				{load_url_in_div id="reviewAttachmentsModal" url=$reviewAttachmentsModalUrl}
			{/if}

			<div>
				<p>{translate key="reviewer.submission.reviewRequestDate"}: {$reviewAssignment->getDateNotified()|date_format:$dateFormatShort}</p>
				<p>{translate key="reviewer.submission.responseDueDate"}: {$reviewAssignment->getDateResponseDue()|date_format:$dateFormatShort}</p>
				<p>{translate key="reviewer.submission.reviewDueDate"}: {$reviewAssignment->getDateDue()|date_format:$dateFormatShort}</p>
				<p>{translate key="common.dateCompleted"}: {$reviewAssignment->getDateCompleted()|date_format:$dateFormatShort}</p>
			</div>

			{if $displayFilesGrid}
				{capture assign="reviewFilesModalUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.ReviewerReviewFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false}{/capture}
				{load_url_in_div id="reviewFilesModal" url=$reviewFilesModalUrl}
			{/if}
		{else}
			<h4>{translate key="reviewer.submission.reviewDeclineDate"}:</h4>
			<div>
				<p>{$reviewAssignment->getDateConfirmed()|date_format:$dateFormatShort}</p>
			</div>
			<h4>{translate key="reviewer.submission.emailLog"}:</h4>
			{if isset($declineEmail)}
				<div>
					<p>{$declineEmail->getSubject()}<br />
						{$declineEmail->getBody()}
					</p>
				</div>
			{else}
				<p>{translate key="reviewer.submission.emailLog.defaultMessage"}</p>
			{/if}
		{/if}
	</div>
	<form class="pkp_form" id="okButtonForm" method="post" action="{url op="closeModal"}">
		{fbvFormSection class="formButtons form_buttons"}
			{assign var=cancelButtonId value="cancelFormButton"|concat:"-"|uniqid}
			<a href="#" id="{$cancelButtonId}" class="pkp_button">{translate key="common.ok"}</a>
			<span class="pkp_spinner"></span>
		{/fbvFormSection}
	</form>
</div>
