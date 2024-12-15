{**
 * templates/controllers/grid/settings/library/form/newFileForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
					uploadUrl: {url|json_encode op="uploadFile" fileType=$fileType escape=false},
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="uploadForm" action="{url op="saveFile"}" method="post">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="libraryFileUploadNotification"}
	<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
	{fbvFormArea id="name"}
		{fbvFormSection title="common.name" required=true}
			{fbvElement type="text" multilingual="true" id="libraryFileName" value=$libraryFileName maxlength="255" required=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="type"}
		{fbvFormSection title="common.type" required=true}
			{fbvElement type="select" from=$fileTypes id="fileType" selected=$fileType defaultValue="" defaultLabel="common.chooseOne"|translate required=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="description"}
		{fbvFormSection title="common.description" required=true}
			{fbvElement type="textarea" multilingual="true" id="description" value=$description}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="file"}
		{fbvFormSection title="common.file" required=true}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormSection list="true" translate=false}
		{capture assign=enablePublicAccess}{translate key="common.publicAccess"}{/capture}
		{fbvElement type="checkbox" id="publicAccess" value="1" checked=false label=$enablePublicAccess translate=false}
		<p>
			{capture assign=downloadUrl}{url router=PKP\core\PKPApplication::ROUTE_PAGE page="libraryFiles" op="downloadPublic" path="id"}{/capture}
			{translate key="settings.libraryFiles.public.viewInstructions" downloadUrl=$downloadUrl}
		</p>
	{/fbvFormSection}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons}
</form>
