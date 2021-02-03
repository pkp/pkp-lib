{**
 * templates/controllers/modals/editorDecision/form/initiateExternalReviewForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form used to initiate the first review round.
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#initiateReview').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', null);
	{rdelim});
</script>

<form class="pkp_form" id="initiateReview" method="post" action="{url op="saveExternalReview"}" >
	<p>{translate key="editor.submission.externalReviewDescription"}</p>

	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />

	<!-- Available submission files -->
	{capture assign=filesForReviewUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SelectableSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
	{load_url_in_div id="filesForReviewGrid" url=$filesForReviewUrl}
	{fbvFormButtons submitText="editor.submission.decision.sendExternalReview"}
</form>
