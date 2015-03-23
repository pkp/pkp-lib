{**
 * templates/controllers/wizard/fileUpload/form/supplementaryFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Supplementary file metadata form.
 *
 * Parameters:
 *  $submissionFile: The submission artwork file.
 *  $stageId: The workflow stage id from which the upload
 *   wizard was called.
 *  $showButtons: True iff form buttons should be presented.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#metadataForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="metadataForm" action="{url component="api.file.ManageFileApiHandler" op="saveMetadata" submissionId=$submissionFile->getSubmissionId() stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId() escape=false}" method="post">

	{* Editable metadata *}
	{fbvFormArea id="fileMetaData"}
		{fbvFormSection title="submission.form.name" required=true}
			{fbvElement type="text" id="name" value=$submissionFile->getLocalizedName() maxlength="255"}
		{/fbvFormSection}

		{fbvFormSection class="border"}
			{fbvElement label="submission.supplementary.creator" type="text" id="name" value=$submissionFile->getLocalizedCreator() maxlength="255"}
		{/fbvFormSection}
	{/fbvFormArea}

	{* Read-only meta-data *}
	{fbvFormArea id="fileInfo" title="submission.submit.fileInformation" class="border"}
		{fbvFormSection title="common.fileName" inline=true size=$fbvStyles.size.MEDIUM}
			{$submissionFile->getClientFileName()|escape}
		{/fbvFormSection}
		{fbvFormSection title="common.fileType" inline=true size=$fbvStyles.size.MEDIUM}
			{$submissionFile->getExtension()|escape}
		{/fbvFormSection}
		{fbvFormSection title="common.fileSize" inline=true size=$fbvStyles.size.MEDIUM}
			{$submissionFile->getNiceFileSize()}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $showButtons}
		{fbvElement type="hidden" id="showButtons" value=$showButtons}
		{fbvFormButtons submitText="common.save"}
	{/if}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
