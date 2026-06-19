{**
 * templates/reviewer/review/modal/regretMessage.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display a field for reviewers to enter regret messages
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#declineReviewForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		// Copy competing interests from the still-live Step 1 form into this decline form.
		$('#declineCompetingInterestOption').val($('input[name="competingInterestOption"]:checked').val() || 'noCompetingInterests');
		var ciTextarea = $('textarea[name="reviewerCompetingInterests"]');
		var ciEditor = (typeof tinymce !== 'undefined' && ciTextarea.length) ? tinymce.get(ciTextarea.attr('id')) : null;
		$('#declineReviewerCompetingInterests').val(ciEditor ? ciEditor.getContent() : ciTextarea.val());
	{rdelim});
</script>

<form class="pkp_form" id="declineReviewForm" method="post" action="{url op="saveDeclineReview" path=$submissionId|escape}">
	{csrf}
	<input type="hidden" id="declineCompetingInterestOption" name="competingInterestOption" value="">
	<input type="hidden" id="declineReviewerCompetingInterests" name="reviewerCompetingInterests" value="">
	<p>{translate key="reviewer.submission.declineReviewMessage"}</p>

	{fbvFormArea id="declineReview"}
		{fbvFormSection}
			{fbvElement type="textarea" id="declineReviewMessage" value=$declineMessageBody rich=true}
		{/fbvFormSection}

		{fbvFormButtons submitText="reviewer.submission.declineReview" hideCancel=true}
	{/fbvFormArea}
</form>
