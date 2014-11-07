{**
 * templates/user/form/profileForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#profile').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
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

	{if $currentContext && ($allowRegAuthor || $allowRegReviewer)}
		{fbvFormSection label="user.register.registerAs" list="true"}
			{if $allowRegAuthor}
				{iterate from=authorUserGroups item=userGroup}
					{assign var="userGroupId" value=$userGroup->getId()}
					{if in_array($userGroup->getId(), $userGroupIds)}
						{assign var="checked" value=true}
					{else}
						{assign var="checked" value=false}
					{/if}
					{if $userGroup->getPermitSelfRegistration()}
						{fbvElement type="checkbox" id="authorGroup-$userGroupId" name="authorGroup[$userGroupId]" checked=$checked label=$userGroup->getLocalizedName() translate=false}
					{/if}
				{/iterate}
			{/if}
			{if $allowRegReviewer}
				{iterate from=reviewerUserGroups item=userGroup}
					{assign var="userGroupId" value=$userGroup->getId()}
					{if in_array($userGroup->getId(), $userGroupIds)}
						{assign var="checked" value=true}
					{else}
						{assign var="checked" value=false}
					{/if}
					{if $userGroup->getPermitSelfRegistration()}
						{fbvElement type="checkbox" id="reviewerGroup-$userGroupId" name="reviewerGroup[$userGroupId]" checked=$checked label=$userGroup->getLocalizedName() translate=false}
					{/if}
				{/iterate}
			{/if}
		{/fbvFormSection}
	{/if}

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
