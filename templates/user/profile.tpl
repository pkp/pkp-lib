{**
 * templates/user/profile.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
{strip}
{assign var="pageTitle" value="user.profile.editProfile"}
{include file="common/header.tpl"}
{/strip}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#profile').pkpHandler('$.pkp.controllers.form.FormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="profile" method="post" action="{url op="saveProfile"}" enctype="multipart/form-data">
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
	<div id="userFormCompactLeftContainer" class="pkp_helpers_clear">
		{fbvFormArea id="userFormCompactLeft"}
			{fbvFormSection title="common.name"}
				{fbvElement type="text" label="user.firstName" required="true" id="firstName" value=$firstName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
				{fbvElement type="text" label="user.middleName" id="middleName" value=$middleName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
				{fbvElement type="text" label="user.lastName" required="true" id="lastName" value=$lastName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
				{fbvElement type="text" label="user.suffix" id="suffix" value=$suffix size=$fbvStyles.size.SMALL inline=true}
			{/fbvFormSection}

			{fbvFormSection title="about.contact"}
				{fbvElement type="text" label="user.email" id="email" required="true" value=$email maxlength="90" size=$fbvStyles.size.MEDIUM inline=true}
				{fbvElement type="select" label="common.country" name="country" id="country" defaultLabel="" defaultValue="" from=$countries selected=$country translate="0" size=$fbvStyles.size.MEDIUM inline=true required=true}
			{/fbvFormSection}
			{fbvFormSection for="country"}
			{/fbvFormSection}
		{/fbvFormArea}
	</div>
	{capture assign="extraContent"}
		<div id="userFormExtendedContainer" class="full left">
			{fbvFormArea id="userFormExtendedLeft"}
				{fbvFormSection}
					{fbvElement type="text" label="user.salutation" name="salutation" id="salutation" value=$salutation maxlength="40" inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="select" label="user.gender" name="gender" id="gender" defaultLabel="" defaultValue="" from=$genderOptions translate="true" selected=$gender inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="text" label="user.initials" name="initials" id="initials" value=$initials maxlength="5" inline=true size=$fbvStyles.size.SMALL}
				{/fbvFormSection}

				{fbvFormSection}
					{fbvElement type="text" label="user.url" name="userUrl" id="userUrl" value=$userUrl maxlength="255" inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="text" label="user.phone" name="phone" id="phone" value=$phone maxlength="24" inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="text" label="user.fax" name="fax" id="fax" value=$fax maxlength="24" inline=true size=$fbvStyles.size.SMALL}
				{/fbvFormSection}

				{if count($availableLocales) > 1}
					{fbvFormSection title="user.workingLanguages" list=true}
						{foreach from=$availableLocales key=localeKey item=localeName}
							{if $userLocales && in_array($localeKey, $userLocales)}
								{assign var="checked" value="true"}
							{else}
								{assign var="checked" value="false"}
							{/if}
							{fbvElement type="checkbox" name="userLocales[]" id="userLocales-$localeKey" value="$localeKey" checked=$checked label="$localeName" translate=false }
						{/foreach}
					{/fbvFormSection}
				{/if}

				{if $allowRegReviewer}
					{fbvFormSection for="interests"}
						{fbvElement type="interests" id="interests" interestsKeywords=$interestsKeywords interestsTextOnly=$interestsTextOnly label="user.interests"}
					{/fbvFormSection}
				{/if}

				{fbvFormSection for="affiliation"}
					{fbvElement type="text" label="user.affiliation" multilingual="true" name="affiliation" id="affiliation" value=$affiliation inline=true size=$fbvStyles.size.LARGE}
				{/fbvFormSection}

				{fbvFormSection}
					{capture assign="biographyLabel"}{translate key="user.biography"} {translate key="user.biography.description"}{/capture}
					{fbvElement type="textarea" label="$biographyLabel" multilingual=true name="biography" id="biography" value=$biography subLabelTranslate=false inline=true size=$fbvStyles.size.MEDIUM rich=true}
					{fbvElement type="textarea" label="common.mailingAddress" name="mailingAddress" id="mailingAddress" value=$mailingAddress inline=true size=$fbvStyles.size.MEDIUM rich=true}
				{/fbvFormSection}

				{fbvFormSection}
					{fbvElement type="textarea" label="user.signature" multilingual="true" name="signature" id="signature" value=$signature size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
			{/fbvFormArea}
		</div>
	{/capture}
	<div id="userExtraFormFields" class="left full">
		{include file="controllers/extrasOnDemand.tpl"
			id="userExtras"
			widgetWrapper="#userExtraFormFields"
			moreDetailsText="grid.user.moreDetails"
			lessDetailsText="grid.user.lessDetails"
			extraContent=$extraContent
		}
	</div>

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
					{fbvElement type="checkbox" id="authorGroup-$userGroupId" name="authorGroup[$userGroupId]" checked=$checked label=$userGroup->getLocalizedName() translate=false}
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
					{fbvElement type="checkbox" id="reviewerGroup-$userGroupId" name="reviewerGroup[$userGroupId]" checked=$checked label=$userGroup->getLocalizedName() translate=false}
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

{include file="common/footer.tpl"}
