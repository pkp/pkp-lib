{**
 * templates/manager/reviewForms/previewReviewForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Preview of a review form.
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#previewReviewForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler',
			{ldelim}
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="previewReviewForm" method="post" action="#">
	<h3>{$title|escape}</h3>
	<p>{$description}</p>

	{include file="reviewer/review/reviewFormResponse.tpl"}
</form>
