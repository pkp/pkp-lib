{**
 * templates/user/profile.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
{include file="common/header.tpl" pageTitle="user.profile.editProfile"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#profile').pkpHandler('$.pkp.controllers.form.FormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="profile" method="post" action="{url op="saveProfile"}" enctype="multipart/form-data">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="userProfileNotification"}

	<div id="userFormContainer">
		<div id="userDetails" class="full left">
			{fbvFormArea id="userNameInfo"}
				{fbvFormSection title="user.username"}
					{$username|escape}
				{/fbvFormSection}

				{fbvFormSection title="user.password"}
					<a href="{url op='changePassword'}">{translate key="user.changePassword"}</a>
				{/fbvFormSection}
			{/fbvFormArea}
	</div>

	{include
		file="common/userDetails.tpl"
		disableUserNameSection=true
		disableEmailWithConfirmSection=true
		disableAuthSourceSection=true
		disablePasswordSection=true
		disableSendNotifySection=true
		countryRequired=true
	}

	{include file="user/userGroups.tpl"}

	{** FIXME 6760: Fix profile image uploads
	{fbvFormSection id="profileImage" label="user.profile.form.profileImage"}
		{fbvFileInput id="profileImage" submit="uploadProfileImage"}
		{if $profileImage}
			{translate key="common.fileName"}: {$profileImage.name|escape} {$profileImage.dateUploaded|date_format:$datetimeFormatShort} <input type="submit" name="deleteProfileImage" value="{translate key="common.delete"}" class="button" />
			<br />
			<img src="{$sitePublicFilesDir}/{$profileImage.uploadName|escape:"url"}" width="{$profileImage.width|escape}" height="{$profileImage.height|escape}" style="border: 0;" alt="{translate key="user.profile.form.profileImage"}" />
		{/if}
	{/fbvFormSection}**}

	{$additionalProfileFormContent}

	{url|assign:cancelUrl page="dashboard"}
	{fbvFormButtons submitText="common.save" cancelUrl=$cancelUrl}
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

{include file="common/footer.tpl"}
