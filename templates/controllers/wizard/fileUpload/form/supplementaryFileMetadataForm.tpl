{**
 * templates/controllers/wizard/fileUpload/form/supplementaryFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
{assign var=metadataFormId value="metadataForm"|uniqid}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#{$metadataFormId}').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="{$metadataFormId}" action="{url component="api.file.ManageFileApiHandler" op="saveMetadata" submissionId=$submissionFile->getSubmissionId() stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId() escape=false}" method="post">
	{csrf}

	{* Editable metadata *}
	{fbvFormArea id="fileMetaData"}

		{* File detail summary *}
		{fbvFormSection}
			{include file="controllers/wizard/fileUpload/form/uploadedFileSummary.tpl" submissionFile=$submissionFile}
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement label="common.description" type="textarea" id="description" value=$submissionFile->getDescription(null) multilingual=true}
			{fbvElement label="submission.supplementary.creator" inline=true size=$fbvStyles.size.MEDIUM type="text" id="creator" value=$submissionFile->getCreator(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.publisher" inline=true size=$fbvStyles.size.MEDIUM type="text" id="publisher" value=$submissionFile->getPublisher(null) multilingual=true maxlength="255"}
			{fbvElement label="common.source" inline=true size=$fbvStyles.size.MEDIUM type="text" id="source" value=$submissionFile->getSource(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.subject" inline=true size=$fbvStyles.size.MEDIUM type="text" id="subject" value=$submissionFile->getSubject(null) multilingual=true maxlength="255"}
			{fbvElement label="submission.supplementary.sponsor" inline=true size=$fbvStyles.size.MEDIUM type="text" id="sponsor" value=$submissionFile->getSponsor(null) multilingual=true maxlength="255"}
			{fbvElement label="common.date" inline=true size=$fbvStyles.size.SMALL type="text" id="dateCreated" value=$submissionFile->getDateCreated(null) class="datepicker"}
			{fbvElement label="common.language" inline=true size=$fbvStyles.size.SMALL type="text" id="language" value=$submissionFile->getLanguage() maxlength="255"}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $submissionFile->supportsDependentFiles()}
		{capture assign=dependentFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionFile->getSubmissionId() fileId=$submissionFile->getFileId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{if $showButtons}
		{fbvElement type="hidden" id="showButtons" value=$showButtons}
		{fbvFormButtons submitText="common.save"}
	{/if}
</form>
