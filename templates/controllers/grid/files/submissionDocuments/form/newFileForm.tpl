{**
 * templates/controllers/grid/files/submissionDocuments/form/newFileForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Library Files form
 *}

<script type="text/javascript">
	// Attach the file upload form handler.
	$(function() {ldelim}
		$('#uploadForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode op="uploadFile" fileType=$fileType submissionId=$submissionId escape=false},
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="uploadForm" action="{url op="saveFile"}" method="post">
	{help file="chapter5/submission-library/add-a-file.md" class="pkp_helpers_align_right"}
	<div class="pkp_helpers_clear"></div>

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="libraryFileUploadNotification"}
	<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	{fbvFormArea id="name"}
		{fbvFormSection title="common.name" required=true}
			{fbvElement type="text" multilingual="true" id="libraryFileName" value=$libraryFileName maxlength="255"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="type"}
		{fbvFormSection title="common.type" required=true}
			{translate|assign:"defaultLabel" key="common.chooseOne"}
			{fbvElement type="select" from=$fileTypes id="fileType" selected=$fileType defaultValue="" defaultLabel=$defaultLabel}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="file"}
		{fbvFormSection title="common.file" required=true}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
