{**
 * templates/controllers/grid/users/reviewer/readReview.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Screen to let user read a review.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#readReviewForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="readReviewForm" method="post" action="{url op="reviewRead"}">
	<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignment->getId()|escape}" />
	<input type="hidden" name="submissionId" value="{$reviewAssignment->getSubmissionId()|escape}" />
	<input type="hidden" name="stageId" value="{$reviewAssignment->getStageId()|escape}" />
	<p>{translate key="editor.review.readConfirmation"}</p>
	<div id="reviewAssignment-{$reviewAssignment->getId()|escape}">
		<table width="100%">
			<tr valign="top">
				<td>
					{$reviewAssignment->getReviewerFullName()|escape}<br />
					<span class="pkp_controllers_informationCenter_itemLastEvent">{$reviewAssignment->getDateCompleted()|date_format:$datetimeFormatShort}</span>
				</td>
			</tr>
			{if $reviewAssignment->getReviewFormId()}
				{** FIXME: add review forms **}
			{else}
				<tr valign="top">
					<td>
						{assign var="contents" value=$reviewerComment->getComments()}
						<br />
						<span>
							{$contents|truncate:250|nl2br|strip_unsafe_html}
							{if $contents|strlen > 250}<a href="javascript:$.noop();" class="showMore">{translate key="common.more"}</a>{/if}
						</span>
						{if $contents|strlen > 250}
							<span class="hidden">
								{$contents|nl2br|strip_unsafe_html} <a href="javascript:$.noop();" class="showLess">{translate key="common.less"}</a>
							</span>
						{/if}
						<br /><br />
					</td>
				</tr>
			{/if}
			{if $reviewAssignment->getRecommendation()}
				<tr>
					<td>{translate key="editor.article.recommendation"}:
					{$reviewAssignment->getLocalizedRecommendation()}</td>
				</tr>
			{/if}
			{if $reviewAssignment->getCompetingInterests()}
				<tr valign="top"><td><br />
					{$reviewAssignment->getCompetingInterests()|nl2br|strip_unsafe_html}
				</td></tr>
			{/if}
		</table>
	</div>
	{fbvFormArea id="readReview"}
		{fbvFormSection}
			{url|assign:reviewAttachmentsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submission->getId() reviewId=$reviewAssignment->getId() stageId=$reviewAssignment->getStageId() escape=false}
			{load_url_in_div id="readReviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
		{/fbvFormSection}
		{fbvFormButtons id="closeButton" hideCancel=false submitText="common.confirm"}
	{/fbvFormArea}
</form>
