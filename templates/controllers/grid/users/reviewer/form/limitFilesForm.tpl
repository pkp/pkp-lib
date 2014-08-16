{**
 * templates/controllers/grid/user/reviewer/form/limitFilesForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Limit the review files available to a reviewer who has already been
 * assigned to a submission.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#limitFilesForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.LimitFilesFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="limitFilesForm" method="post" action="{url op="updateLimitFiles"}">
	<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignmentId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

	{include file="controllers/grid/users/reviewer/form/noFilesWarning.tpl"}

	{url|assign:limitReviewFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.LimitReviewFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignmentId escape=false}
	{load_url_in_div id="limitReviewFilesGrid" url=$limitReviewFilesGridUrl}

	{fbvFormButtons submitText="editor.submissionReview.restrictFiles"}
</form>
