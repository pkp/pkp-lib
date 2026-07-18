{**
 * templates/controllers/grid/users/reviewer/form/reviewCancelForm.tpl
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Cancel review form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#cancelReviewForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.ReviewerActionFormHandler',
			{ldelim}
				templateUrl: {url|json_encode
					router=PKP\core\PKPApplication::ROUTE_COMPONENT
					component='grid.users.reviewer.ReviewerGridHandler'
					op='fetchReviewerActionTemplateBody'
					stageId=$stageId
					reviewRoundId=$reviewRoundId
					submissionId=$submissionId
					reviewAssignmentId=$reviewAssignmentId
					defaultTemplate=$defaultTemplateKey
					escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="cancelReviewForm" method="post" action="{url op="updateCancelReview"}" >
	{csrf}
	<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignmentId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
	<input type="hidden" name="reviewerId" value="{$reviewerId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	{fbvFormArea id="notifyFormArea"}
		{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
			{fbvElement type="select" from=$templates translate=false id="template" selected=$defaultTemplateKey}
		{/fbvFormSection}

		{fbvFormSection title="editor.review.personalMessageToReviewer" for="personalMessage"}
			{fbvElement type="textarea" id="personalMessage" value=$personalMessage rich=true}
		{/fbvFormSection}
		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{/fbvFormArea}

	{fbvFormSection for="skipEmail" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipEmail" name="skipEmail" label="editor.review.skipEmail"}
	{/fbvFormSection}

	{fbvFormButtons submitText="editor.review.cancelReviewer"}
</form>
