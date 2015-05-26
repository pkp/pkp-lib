{**
 * templates/controllers/grid/files/query/manageQueryFiles.tpl
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
		$('#manageQueryFilesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<!-- Current query files -->
<p>{translate key="editor.submission.query.manageQueryFilesDescription"}</p>

<div id="existingFilesContainer">
	<form class="pkp_form" id="manageQueryFilesForm" action="{url component="grid.files.query.ManageQueryFilesGridHandler" op="updateQueryFiles" submissionId=$submissionId queryId=$queryId stageId=$smarty.const.WORKFLOW_STAGE_ID_EDITING}" method="post">
		{fbvFormArea id="manageQueryFiles"}
			{fbvFormSection}
				{url|assign:manageQueryFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.query.ManageQueryFilesGridHandler" op="fetchGrid" submissionId=$submissionId queryId=$queryId escape=false}
				{load_url_in_div id="manageQueryFilesGrid" url=$manageQueryFilesGridUrl}
			{/fbvFormSection}

			{fbvFormButtons}
		{/fbvFormArea}
	</form>
</div>
