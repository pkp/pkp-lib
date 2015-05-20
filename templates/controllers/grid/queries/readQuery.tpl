{**
 * templates/controllers/grid/queries/readQuery.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Read a query.
 *
 *}
<div class="readQuery">
	{url|assign:queryNotesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueryNotesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId queryId=$query->getId() escape=false}
	{load_url_in_div id="queryNotesGrid" url=$queryNotesGridUrl}

	<script>
		// Attach the handler.
		$(function() {ldelim}
			$('#noteForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
	</script>

	<form class="pkp_form" id="noteForm" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueryNotesGridHandler" op="insertNote" submissionId=$submission->getId() stageId=$stageId queryId=$query->getId() escape=false}" method="post">

		{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
			{fbvElement type="textarea" id="comment" rich=true value=$comment}
		{/fbvFormSection}

		{fbvFormButtons id="addNoteButton"}
	</form>
</div>
