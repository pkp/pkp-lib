{**
 * templates/controllers/wizard/fileUpload/form/supplementaryFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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
			{fbvElement type="text" id="name" value=$submissionFile->getName(null) multilingual=true maxlength="255"}
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement label="common.description" type="textarea" id="description" value=$submissionFile->getDescription(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.creator" inline=true size=$fbvStyles.size.MEDIUM type="text" id="creator" value=$submissionFile->getCreator(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.publisher" inline=true size=$fbvStyles.size.MEDIUM type="text" id="publisher" value=$submissionFile->getPublisher(null) multilingual=true maxlength="255"}
			{fbvElement label="common.source" inline=true size=$fbvStyles.size.MEDIUM type="text" id="source" value=$submissionFile->getSource(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.subject" inline=true size=$fbvStyles.size.MEDIUM type="text" id="subject" value=$submissionFile->getSubject(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.sponsor" inline=true size=$fbvStyles.size.MEDIUM type="text" id="sponsor" value=$submissionFile->getSponsor(null) multilingual=true maxlength="255"}
			{fbvElement label="common.date" inline=true size=$fbvStyles.size.SMALL type="text" id="dateCreated" value=$submissionFile->getDateCreated(null) class="datepicker"}
			{fbvElement label="common.language" inline=true size=$fbvStyles.size.SMALL type="text" id="language" value=$submissionFile->getLanguage() maxlength="255"}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $showButtons}
		{fbvElement type="hidden" id="showButtons" value=$showButtons}
		{fbvFormButtons submitText="common.save"}
	{/if}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
