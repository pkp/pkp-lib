{**
 * templates/controllers/grid/users/reviewer/authorReadReview.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Screen to let an author read an open review.
 *
 *}

{* Form handler attachment implemented in application-specific versions of this template. *}

<form class="pkp_form" id="readReviewForm" method="post" action="">
	{csrf}

	{fbvFormSection}
		<div id="reviewAssignment-{$reviewAssignment->getId()|escape}">
			<h2>{$reviewAssignment->getReviewerFullName()|escape}</h2>

			{if $reviewAssignment->getDateCompleted()}
				{fbvFormSection}
					<div class="pkp_controllers_informationCenter_itemLastEvent">
						{translate key="common.completed.date" dateCompleted=$reviewAssignment->getDateCompleted()|date_format:$datetimeFormatShort}
					</div>
				{/fbvFormSection}

				{if $reviewAssignment->getRecommendation()}
					{fbvFormSection}
						<div class="pkp_controllers_informationCenter_itemLastEvent">
							{translate key="submission.recommendation" recommendation=$reviewAssignment->getLocalizedRecommendation()}
						</div>
					{/fbvFormSection}
				{/if}

				{if $reviewAssignment->getReviewFormId()}
					{include file="reviewer/review/reviewFormResponse.tpl"}
				{elseif $comments->getCount()}
					<h3>{translate key="editor.review.reviewerComments"}</h3>
					{iterate from=comments item=comment}
						<h4>{translate key="submission.comments.canShareWithAuthor"}</h4>
						{include file="controllers/revealMore.tpl" content=$comment->getComments()|strip_unsafe_html}
					{/iterate}
				{/if}
			{/if}
		</div>
	{/fbvFormSection}

	<div class="pkp_notification" id="noFilesWarning" style="display: none;">
		{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=noFilesWarningContent notificationStyleClass=notifyWarning notificationTitle="editor.review.noReviewFilesUploaded"|translate notificationContents="editor.review.noReviewFilesUploaded.details"|translate}
	</div>

	{fbvFormArea id="readReview"}
		{fbvFormSection title="reviewer.submission.reviewerFiles"}
			{capture assign="reviewAttachmentsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.AuthorOpenReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submission->getId() reviewId=$reviewAssignment->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewAssignment->getReviewRoundId() escape=false}{/capture}
			{load_url_in_div id="readReviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
		{/fbvFormSection}
	{/fbvFormArea}

</form>
