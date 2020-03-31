{**
 * templates/controllers/modals/editorDecision/form/newReviewRoundForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form used to create a new review round (after the first round)
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#newRoundForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', null);
	{rdelim});
</script>

<form class="pkp_form" id="newRoundForm" method="post" action="{url op="saveNewReviewRound"}" >
	<p>{translate key="editor.submission.newRoundDescription"}</p>

	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
	<input type="hidden" name="decision" value="{$smarty.const.SUBMISSION_EDITOR_DECISION_NEW_ROUND}" />

	<!-- Revision files grid (Displays only revisions at first, and hides all other files which can then be displayed with filter button -->
	{capture assign=newRoundRevisionsUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.SelectableReviewRevisionsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
	{load_url_in_div id="newRoundRevisionsGrid" url=$newRoundRevisionsUrl}

	{fbvFormButtons submitText="editor.submission.createNewRound"}
</form>
