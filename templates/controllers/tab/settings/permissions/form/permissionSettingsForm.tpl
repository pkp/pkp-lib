{**
 * controllers/tab/settings/permissionSettings/form/permissionSettingsForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Indexing management form.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#permissionSettingsForm').pkpHandler(
			'$.pkp.controllers.tab.settings.permissions.form.PermissionSettingsFormHandler',
			{ldelim}
				resetPermissionsUrl: '{url|escape:"javascript" op="resetPermissions" escape=false}',
				resetPermissionsConfirmText: '{translate|escape:"javascript" key="manager.setup.resetPermissions.confirm"}',
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="permissionSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.DistributionSettingsTabHandler" op="saveFormData" tab="permissions"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="permissionSettingsFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	{fbvFormArea id="permissionSettings"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="copyrightNoticeAgree" value="1" checked=$copyrightNoticeAgree label="manager.setup.authorCopyrightNoticeAgree"}
			{fbvElement type="checkbox" id="includeCopyrightStatement" value="1" checked=$includeCopyrightStatement label="manager.setup.includeCopyrightStatement"}
			{fbvElement type="checkbox" id="includeLicense" value="1" checked=$includeLicense label="manager.setup.includeLicense"}
		{/fbvFormSection}

		{fbvFormSection label="manager.setup.authorCopyrightNotice"|translate description=$authorCopyrightNoticeDescription translate=false}
			{fbvElement type="textarea" multilingual=true name="copyrightNotice" id="copyrightNotice" value=$copyrightNotice rich=true}
		{/fbvFormSection}

		{$additionalFormContent}
	{/fbvFormArea}

	{fbvFormArea id="copyrightHolderSettings" title="submission.copyrightHolder" class="border"}
		{fbvFormSection list=true size=$fbvStyles.size.MEDIUM inline=true}
			{fbvElement type="radio" id="copyrightHolderType-author" name="copyrightHolderType" value="author" checked=$copyrightHolderType|compare:"author" label="user.role.author"}
			{fbvElement type="radio" id="copyrightHolderType-context" name="copyrightHolderType" value="context" checked=$copyrightHolderType|compare:"context" label="context.context"}
			{fbvElement type="radio" id="copyrightHolderType-author" name="copyrightHolderType" value="other" checked=$copyrightHolderType|compare:"other" label="common.other"}
		{/fbvFormSection}
		{fbvFormSection size=$fbvStyles.size.MEDIUM inline=true}
			{fbvElement type="text" id="copyrightHolderOther" name="copyrightHolderOther" value=$copyrightHolderOther multilingual=true label="common.other" disabled=$copyrightHolderType|compare:"other":false:true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="licenseSettings"}
		{fbvFormSection title="submission.license"}
			{fbvElement type="select" id="licenseURLSelect" from=$ccLicenseOptions selected=$licenseURL label="manager.setup.licenseURLDescription" size=$fbvStyles.size.MEDIUM inline=true}
			{fbvElement type="text" id="licenseURL" name="licenseURL" value=$licenseURL label="common.url" size=$fbvStyles.size.MEDIUM inline=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{if !$wizardMode}
		{fbvFormSection class="formButtons"}
			<p>{translate key="manager.setup.resetPermissions.description"}</p>
			{fbvElement type="button" class="pkp_helpers_align_left" id="resetPermissionsButton" label="manager.setup.resetPermissions"}
			{assign var=buttonId value="submitFormButton"|concat:"-"|uniqid}
			{fbvElement type="submit" class="submitFormButton" id=$buttonId label="common.save"}
		{/fbvFormSection}
	{/if}
</form>
