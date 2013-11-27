{**
 * templates/controllers/wizard/fileUpload/form/metadataForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * File metadata form.
 *
 * Parameters:
 *  $submissionFile: The submission or artwork file.
 *  $stageId: The workflow stage id from which the upload
 *   wizard was called.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#metadataForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="metadataForm" action="{url op="saveMetadata" submissionId=$submissionFile->getSubmissionId() stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId() escape=false}" method="post">

	{* Editable metadata *}
	{fbvFormArea id="fileMetaData"}
		{fbvFormSection title="submission.form.name" required=true}
			{fbvElement type="text" id="name" value=$submissionFile->getLocalizedName() maxlength="120"}
		{/fbvFormSection}
		{if is_a($submissionFile, 'ArtworkFile')}
			{fbvFormSection title="grid.artworkFile.caption" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCaption" height=$fbvStyles.height.SHORT value=$submissionFile->getCaption()}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.credit" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCredit" height=$fbvStyles.height.SHORT value=$submissionFile->getCredit()}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.copyrightOwner" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCopyrightOwner" height=$fbvStyles.height.SHORT value=$submissionFile->getCopyrightOwner()}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.permissionTerms" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkPermissionTerms" height=$fbvStyles.height.SHORT value=$submissionFile->getPermissionTerms()}
			{/fbvFormSection}
		{/if}
		{fbvFormSection title="submission.upload.noteToAccompanyFile"}
			{fbvElement type="textarea" id="note" height=$fbvStyles.height.SHORT}
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

		{if is_a($submissionFile, 'ArtworkFile') && $submissionFile->getWidth() > 0 && $submissionFile->getHeight() > 0}
			{assign var=dpi value=300}
			{math assign="imageWidthOnDevice" equation="w/dpi" w=$submissionFile->getWidth() dpi=$dpi format="%.2f"}
			{math assign="imageHeightOnDevice" equation="h/dpi" h=$submissionFile->getHeight() dpi=$dpi format="%.2f"}
			{fbvFormSection title="common.quality" inline=true size=$fbvStyles.size.MEDIUM}
				{translate key="common.dimensionsInches" width=$imageWidthOnDevice height=$imageHeightOnDevice dpi=$dpi}
				<br/>
				({translate key="common.dimensionsPixels" width=$submissionFile->getWidth() height=$submissionFile->getHeight()})
			{/fbvFormSection}
			{fbvFormSection title="common.preview" inline=true size=$fbvStyles.size.MEDIUM}
				{if $submissionFile->getFileType() == 'image/tiff'}
					<embed width="100" src="{url component="api.file.FileApiHandler" op="viewFile" submissionId=$submissionFile->getSubmissionId() stageId=$stageId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId()}" type="image/tiff" negative=yes>
				{else}<a target="_blank" href="{url component="api.file.FileApiHandler" op="viewFile" submissionId=$submissionFile->getSubmissionId() stageId=$stageId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId() revision=$submissionFile->getRevision()}">
					<img class="thumbnail" width="100" src="{url component="api.file.FileApiHandler" op="viewFile" submissionId=$submissionFile->getSubmissionId() stageId=$stageId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId()}" />
				</a>{/if}
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
