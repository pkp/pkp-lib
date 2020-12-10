{**
 * templates/controllers/grid/files/final/manageFinalDraftFiles.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Allows editor to add more file to the review (that weren't added when the submission was sent to review)
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#manageFinalDraftFilesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="manageFinalDraftFilesForm" action="{url component="grid.files.final.ManageFinalDraftFilesGridHandler" op="updateFinalDraftFiles" submissionId=$submissionId}" method="post">
	<!-- Current final draft files -->
	<div id="existingFilesContainer">
		{csrf}
		{fbvFormArea id="manageFinalDraftFiles"}
			{fbvFormSection}
				<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
				<input type="hidden" name="stageId" value="{$smarty.const.WORKFLOW_STAGE_ID_EDITING}" />
				{capture assign=availableReviewFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.final.ManageFinalDraftFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
				{load_url_in_div id="availableReviewFilesGrid" url=$availableReviewFilesGridUrl}
			{/fbvFormSection}

			{fbvFormButtons}
		{/fbvFormArea}
	</div>
</form>
