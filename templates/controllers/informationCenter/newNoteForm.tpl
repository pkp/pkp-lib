{**
 * templates/controllers/informationCenter/newNoteForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display submission file notes/note form in information center.
 *}

<script>
	// Attach the Notes handler.
	$(function() {ldelim}
		$('#newNoteForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler',
			{ldelim}
				baseUrl: {$baseUrl|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="newNoteForm" action="{url router=$smarty.const.ROUTE_COMPONENT op="saveNote" params=$linkParams}" method="post">
	<div id="newNoteContainer">
		{csrf}
		{fbvFormSection title="informationCenter.addNote" for="newNote"}
			{fbvElement type="textarea" id="newNote"}
		{/fbvFormSection}
		{fbvFormButtons hideCancel=true submitText=$submitNoteText}
	</div>
</form>
