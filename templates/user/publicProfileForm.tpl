{**
 * templates/user/publicProfileForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Public user profile form.
 *
 * @hook User::PublicProfile::AdditionalItems []
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#publicProfileForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode op="uploadProfileImage" escape=false},
					baseUrl: {$baseUrl|json_encode},
					filters: {ldelim}
						mime_types : [
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png,gif" {rdelim}
						]
					{rdelim},
					multipart_params: {ldelim}
						csrfToken: {csrf type="json"}
					{rdelim},
					resize: {ldelim}
						width: {$profileImageMaxWidth|intval},
						height: {$profileImageMaxHeight|intval},
						crop: true,
					{rdelim}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

{* Form for deleting the profile image (placed here to avoid nesting forms) *}
<form type="post" action="{url op="deleteProfileImage"}" id="deleteProfileImageForm">
	{csrf}
</form>

<form class="pkp_form" id="publicProfileForm" method="post" action="{url op="savePublicProfile"}" enctype="multipart/form-data">
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="publicProfileNotification"}

	{fbvFormSection title="user.profile.form.profileImage"}
		{if $profileImage}
			{* Add a unique ID to prevent caching *}
			<img src="{$baseUrl}/{$publicSiteFilesPath}/{$profileImage.uploadName}?{""|uniqid}" alt="{translate key="user.profile.form.profileImage"}" />
			<div>
				<button onclick="document.getElementById('deleteProfileImageForm').submit(); return false;" class="pkp_button pkp_button_offset">{translate key="common.delete"}</button>
			</div>
		{/if}
	{/fbvFormSection}
	{fbvFormSection}
		{include file="controllers/fileUploadContainer.tpl" id="plupload"}
	{/fbvFormSection}

	{fbvFormSection}
		{fbvElement type="textarea" label="user.biography" multilingual="true" name="biography" id="biography" rich=true value=$biography}
	{/fbvFormSection}
	{fbvFormSection}
		{fbvElement type="text" label="user.url" name="userUrl" id="userUrl" value=$userUrl maxlength="255"}
	{/fbvFormSection}

	{call_hook name="User::PublicProfile::AdditionalItems"}

	<p>
		{capture assign="privacyUrl"}{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>
