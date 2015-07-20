{**
 * templates/controllers/grid/files/copyedit/manageCopyeditFiles.tpl
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
		$('#manageCopyeditFilesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<!-- Current copyedited files -->
<p>{translate key="editor.submission.copyedit.manageCopyeditFilesDescription"}</p>

<div id="existingFilesContainer">
	<form class="pkp_form" id="manageCopyeditFilesForm" action="{url component="grid.files.copyedit.ManageCopyeditFilesGridHandler" op="updateCopyeditFiles" submissionId=$submissionId stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING}" method="post">
		{fbvFormArea id="manageCopyeditFiles"}
			{fbvFormSection}
				{url|assign:manageCopyeditFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.ManageCopyeditFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
				{load_url_in_div id="manageCopyeditFilesGrid" url=$manageCopyeditFilesGridUrl}
			{/fbvFormSection}

			{fbvFormButtons}
		{/fbvFormArea}
	</form>
</div>
