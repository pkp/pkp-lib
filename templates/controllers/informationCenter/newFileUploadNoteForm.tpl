{**
 * templates/controllers/informationCenter/newFileUploadNoteForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display notes/note form with the possibily to upload a file associated with the note
 * in information center.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the upload form handler.
		$('#uploadForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				resetUploader: true,
				uploaderOptions: {ldelim}
					uploadUrl: '{url|escape:javascript op="uploadFile" signoffId=$signoffId submissionId=$submissionId stageId=$stageId escape=false}',
					baseUrl: '{$baseUrl|escape:javascript}'
				{rdelim}
			{rdelim});
	{rdelim});
</script>

<div id="newNoteContainer">
	<form class="pkp_form" id="uploadForm" action="{url router=$smarty.const.ROUTE_COMPONENT op="saveNote" params=$linkParams}" method="post">
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$stageId|escape}" />

		{** Make sure there is at least one available signoff *}
		{if $signoffId}
			{fbvFormArea id="signoff"}
				<input type="hidden" name="symbolic" value="{$symbolic|escape}" />
				<input type="hidden" name="signoffId" value="{$signoffId|escape}" />

				{fbvFormSection title="informationCenter.composeNote" for="newNote"}
					{fbvElement type="textarea" id="newNote"}
				{/fbvFormSection}

				<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
				{fbvFormSection title="submission.submit.selectFile" required=0}
					{* The uploader widget *}
					{include file="controllers/fileUploadContainer.tpl" id="plupload"}
				{/fbvFormSection}
				{fbvFormButtons}
			{/fbvFormArea}
		{else}
			{** Put a marker in place so the form just closes with no attempt to validate **}
			<input type="hidden" name="noSignoffs" value="1" />
			{translate key="submission.signoff.noAvailableSignoffs"}
			{fbvFormButtons id="closeButton" hideCancel=true submitText="common.close"}
		{/if}
	</form>
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</div>
