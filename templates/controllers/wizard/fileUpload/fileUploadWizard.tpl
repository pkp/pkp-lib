{**
 * templates/controllers/wizard/fileUpload/fileUploadWizard.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * A wizard to add files or revisions of files.
 *
 * Parameters:
 *   $submissionId: The submission to which files should be uploaded.
 *   $stageId: The workflow stage from which the wizard was called.
 *   $revisedFileId: A pre-selected file to be revised (optional).
 *}

{assign var=uploadWizardId value="fileUploadWizard"|uniqid}
<script type="text/javascript">
	// Attach the JS file upload wizard handler.
	$(function() {ldelim}
		$('#{$uploadWizardId}').pkpHandler(
			'$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler',
			{ldelim}
				csrfToken: {csrf type=json},
				cancelButtonText: {translate|json_encode key="common.cancel"},
				continueButtonText: {translate|json_encode key="common.continue"},
				finishButtonText: {translate|json_encode key="common.complete"},
				deleteUrl: {url|json_encode component="api.file.ManageFileApiHandler" op="deleteFile" submissionId=$submissionId stageId=$stageId fileStage=$fileStage suppressNotification=true escape=false},
				metadataUrl: {url|json_encode op="editMetadata" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$fileStage assocType=$assocType assocId=$assocId queryId=$queryId escape=false},
				finishUrl: {url|json_encode op="finishFileSubmission" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$fileStage assocType=$assocType assocId=$assocId queryId=$queryId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<div id="{$uploadWizardId}">
	<ul>
		<li><a href="{url op="displayFileUploadForm" submissionId=$submissionId stageId=$stageId uploaderRoles=$uploaderRoles fileStage=$fileStage revisionOnly=$revisionOnly reviewRoundId=$reviewRoundId revisedFileId=$revisedFileId assocType=$assocType assocId=$assocId dependentFilesOnly=$dependentFilesOnly queryId=$queryId}">{translate key="submission.submit.uploadStep"}</a></li>
		<li><a href="metadata">{translate key="submission.submit.metadataStep"}</a></li>
		<li><a href="finish">{translate key="submission.submit.finishingUpStep"}</a></li>
	</ul>
</div>
