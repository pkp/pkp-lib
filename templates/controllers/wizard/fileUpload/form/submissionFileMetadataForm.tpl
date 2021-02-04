{**
 * templates/controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * File metadata form.
 *
 * Parameters:
 *  $submissionFile: The submission file.
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

<form class="pkp_form" id="{$metadataFormId}" action="{url component="api.file.ManageFileApiHandler" op="saveMetadata" submissionId=$submissionFile->getData('submissionId') stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getData('fileStage') submissionFileId=$submissionFile->getId() escape=false}" method="post">
	{csrf}

	{* Editable metadata *}
	{fbvFormArea id="fileMetaData"}

		{* File name and detail summary *}
		{fbvFormSection title="submission.form.name" required=true}
			{fbvElement type="text" id="name" value=$submissionFile->getData('name') multilingual=true maxlength="255" required=true}
		{/fbvFormSection}

		{* Supplementary file metadata *}
		{if $genre && $genre->getCategory() == $smarty.const.GENRE_CATEGORY_SUPPLEMENTARY}
			{fbvFormSection}
				{fbvElement label="common.description" type="textarea" id="description" value=$submissionFile->getData('description') multilingual=true}
				{fbvElement label="submission.supplementary.creator" inline=true size=$fbvStyles.size.MEDIUM type="text" id="creator" value=$submissionFile->getData('creator') multilingual=true maxlength="255"}
				{fbvElement label="submission.supplementary.publisher" inline=true size=$fbvStyles.size.MEDIUM type="text" id="publisher" value=$submissionFile->getData('publisher') multilingual=true maxlength="255"}
				{fbvElement label="common.source" inline=true size=$fbvStyles.size.MEDIUM type="text" id="source" value=$submissionFile->getData('source') multilingual=true maxlength="255"}
				{fbvElement label="submission.supplementary.subject" inline=true size=$fbvStyles.size.MEDIUM type="text" id="subject" value=$submissionFile->getData('subject') multilingual=true maxlength="255"}
				{fbvElement label="submission.supplementary.sponsor" inline=true size=$fbvStyles.size.MEDIUM type="text" id="sponsor" value=$submissionFile->getData('sponsor') multilingual=true maxlength="255"}
				{fbvElement label="common.date" inline=true size=$fbvStyles.size.SMALL type="text" id="dateCreated" value=$submissionFile->getData('dateCreated') class="datepicker"}
				{fbvElement label="common.language" inline=true size=$fbvStyles.size.SMALL type="text" id="language" value=$submissionFile->getData('language') maxlength="255"}
			{/fbvFormSection}
		{/if}

		{* Artwork metadata *}
		{if $genre && $genre->getCategory() == $smarty.const.GENRE_CATEGORY_ARTWORK}
			{fbvFormSection title="grid.artworkFile.caption" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCaption" height=$fbvStyles.height.SHORT value=$submissionFile->getData('caption')}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.credit" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCredit" height=$fbvStyles.height.SHORT value=$submissionFile->getData('credit')}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.copyrightOwner" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkCopyrightOwner" height=$fbvStyles.height.SHORT value=$submissionFile->getData('copyrightOwner')}
			{/fbvFormSection}
			{fbvFormSection title="grid.artworkFile.permissionTerms" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="textarea" id="artworkPermissionTerms" height=$fbvStyles.height.SHORT value=$submissionFile->getData('terms')}
			{/fbvFormSection}
		{/if}

	{/fbvFormArea}

	{if $supportsDependentFiles}
		{capture assign=dependentFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionFile->getData('submissionId') submissionFileId=$submissionFile->getId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{if $showButtons}
		{fbvElement type="hidden" id="showButtons" value=$showButtons}
		{fbvFormButtons submitText="common.save"}
	{/if}
</form>
