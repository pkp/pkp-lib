{**
 * templates/controllers/grid/files/copyedit/manageCopyeditFiles.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Allows users to manage the list of copyedit files, potentially adding more
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#manageCopyeditFilesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="manageCopyeditFilesForm" action="{url component="grid.files.copyedit.ManageCopyeditFilesGridHandler" op="updateCopyeditFiles" submissionId=$submissionId stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING}" method="post">
	<!-- Current copyedited files -->
	<div id="existingFilesContainer">
		{csrf}
		{fbvFormArea id="manageCopyeditFiles"}
			{fbvFormSection}
				{capture assign=manageCopyeditFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.ManageCopyeditFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
				{load_url_in_div id="manageCopyeditFilesGrid" url=$manageCopyeditFilesGridUrl}
			{/fbvFormSection}

			{fbvFormButtons}
		{/fbvFormArea}
	</div>
</form>
