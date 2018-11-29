{**
 * templates/controllers/grid/user/reviewer/form/editReviewForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Limit the review files available to a reviewer who has already been
 * assigned to a submission.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#editReviewForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.EditReviewFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="editReviewForm" method="post" action="{url op="updateReview"}">
	{csrf}
	<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignmentId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

	{fbvFormSection title="editor.review.importantDates"}
		{fbvElement type="text" id="responseDueDate" name="responseDueDate" label="submission.task.responseDueDate" value=$responseDueDate inline=true size=$fbvStyles.size.MEDIUM class="datepicker"}
		{fbvElement type="text" id="reviewDueDate" name="reviewDueDate" label="editor.review.reviewDueDate" value=$reviewDueDate inline=true size=$fbvStyles.size.MEDIUM class="datepicker"}
	{/fbvFormSection}

	{fbvFormSection list=true title="editor.submissionReview.reviewType"}
		{foreach from=$reviewMethods key=methodId item=methodTranslationKey}
			{assign var=elementId value="reviewMethod"|concat:"-"|concat:$methodId}
			{if $reviewMethod == $methodId}
				{assign var=elementChecked value=true}
			{else}
				{assign var=elementChecked value=false}
			{/if}
			{fbvElement type="radio" name="reviewMethod" id=$elementId value=$methodId checked=$elementChecked label=$methodTranslationKey}
		{/foreach}
	{/fbvFormSection}

	{include file="controllers/grid/users/reviewer/form/noFilesWarning.tpl"}

	<h3>{translate key="editor.submissionReview.restrictFiles"}</h3>
	{capture assign=limitReviewFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.LimitReviewFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignmentId escape=false}{/capture}
	{load_url_in_div id="limitReviewFilesGrid" url=$limitReviewFilesGridUrl}

	{if $reviewForms}
		{if count($reviewForms)>0}
			{fbvFormSection title="submission.reviewForm"}
				{fbvElement type="select" name="reviewFormId" id="reviewFormId" defaultLabel="manager.reviewForms.noneChosen"|translate defaultValue="0" translate=false from=$reviewForms selected=$reviewFormId}
			{/fbvFormSection}
		{/if}
	{/if}

	{fbvFormButtons}
</form>
