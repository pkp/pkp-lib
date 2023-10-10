{**
 * templates/controllers/grid/user/reviewer/form/unassignReviewerForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Enroll existing user and assignment reviewer form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#unassignReviewerForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="unassignReviewerForm" method="post" action="{url op="updateUnassignReviewer"}" >
	{csrf}
	<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignmentId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
	<input type="hidden" name="reviewerId" value="{$reviewerId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	<!--  Message to reviewer textarea -->
	{fbvFormSection title="editor.review.personalMessageToReviewer" for="personalMessage"}
		{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true}
	{/fbvFormSection}

	<!-- Skip email checkbox -->
	{fbvFormSection for="skipEmail" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipEmail" name="skipEmail" label="editor.review.skipEmail"}
	{/fbvFormSection}

	{if $dateConfirmed}
		{fbvFormButtons submitText="editor.review.cancelReviewer"}
	{else}
		{fbvFormButtons submitText="editor.review.unassignReviewer"}
	{/if}
</form>
