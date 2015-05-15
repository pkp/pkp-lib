{**
 * templates/controllers/grid/files/copyedit/manageCopyeditedFiles.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Allows editor to add more file to the review (that weren't added when the submission was sent to review)
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#manageCopyeditedFilesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<!-- Current copyedited files -->
<p>{translate key="editor.submission.copyedit.manageCopyeditedFilesDescription"}</p>

<div id="existingFilesContainer">
	<form class="pkp_form" id="manageCopyeditedFilesForm" action="{url component="grid.files.copyedit.ManageCopyeditedFilesGridHandler" op="updateCopyeditedFiles" submissionId=$submissionId}" method="post">
		{fbvFormArea id="manageCopyeditedFiles"}
			{fbvFormSection}
				<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
				<input type="hidden" name="stageId" value="{$smarty.const.WORKFLOW_STAGE_ID_EDITING}" />
				{url|assign:availableReviewFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.ManageCopyeditedFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
				{load_url_in_div id="availableReviewFilesGrid" url=$availableReviewFilesGridUrl}
			{/fbvFormSection}

			{fbvFormButtons}
		{/fbvFormArea}
	</form>
</div>
