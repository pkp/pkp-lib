{**
 * templates/user/publicProfileForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Public user profile form.
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
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="publicProfileForm" method="post" action="{url op="savePublicProfile"}" enctype="multipart/form-data">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="publicProfileNotification"}

	{fbvFormArea id="file" title="user.profile.form.profileImage"}
		{if $profileImage}
			{fbvFormSection size=$fbvStyles.size.SMALL inline=true}
				{* Add a unique ID to prevent caching *}
				<img src="{$baseUrl}/{$publicSiteFilesPath}/{$profileImage.uploadName}?{""|uniqid}" alt="{translate key="user.profile.form.profileImage"}" />
				<a href="{url op="deleteProfileImage"}">{translate key="common.delete"}</a>
			{/fbvFormSection}
		{/if}
		{fbvFormSection size=$fbvStyles.size.MEDIUM inline=true}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormSection}
		{fbvElement type="textarea" label="user.biography" multilingual="true" name="biography" id="biography" rich=true value=$biography size=$fbvStyles.size.LARGE}
		{fbvElement type="text" label="user.url" name="userUrl" id="userUrl" value=$userUrl maxlength="255" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" label="user.orcid" name="orcid" id="orcid" value=$orcid maxlength="36" size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
