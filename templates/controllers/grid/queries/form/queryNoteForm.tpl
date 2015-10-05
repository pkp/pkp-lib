{**
 * templates/controllers/grid/queries/queryNoteForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Read a query.
 *
 *}
<script>
	// Attach the handler.
	$(function() {ldelim}
		$('#noteForm').pkpHandler(
			'$.pkp.controllers.form.CancelActionAjaxFormHandler',
			{ldelim}
				cancelUrl: {url|json_encode op="deleteNote" params=$actionArgs noteId=$noteId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="noteForm" action="{url op="insertNote" params=$actionArgs noteId=$noteId escape=false}" method="post">

	{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
		{fbvElement type="textarea" id="comment" rich=true value=$comment}
	{/fbvFormSection}

	{fbvFormArea id="queryNoteFilesArea"}
		{url|assign:queryNoteFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.query.QueryNoteFilesGridHandler" op="fetchGrid" params=$actionArgs noteId=$noteId escape=false}
		{load_url_in_div id="queryNoteFilesGrid" url=$queryNoteFilesGridUrl}
	{/fbvFormArea}

	{fbvFormButtons id="addNoteButton"}
</form>
