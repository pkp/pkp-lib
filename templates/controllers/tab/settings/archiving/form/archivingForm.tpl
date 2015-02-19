{**
 * controllers/tab/settings/archiving/form/archivingForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Archiving settings form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#archivingForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', {ldelim}
			baseUrl: '{$baseUrl|escape:"javascript"}'
		{rdelim});
	{rdelim});
</script>

<form id="archivingForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="archiving"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="archivingFormNotification"}

	{fbvFormArea id="archivingLockss" class="border"}
		{fbvFormSection description="manager.setup.lockssDescription"}
		{/fbvFormSection}

		{url|assign:"lockssExistingArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_EXISTING_ARCHIVE"}
		{url|assign:"lockssNewArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_NEW_ARCHIVE"}
		{translate|assign:"lockssRegisterDescription" key="manager.setup.lockssRegister" lockssExistingArchiveUrl=$lockssExistingArchiveUrl lockssNewArchiveUrl=$lockssNewArchiveUrl}
		{fbvFormSection list="true" description=$lockssRegisterDescription translate=false}
			{url|assign:"lockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="lockss"}
			{translate|assign:"enableLockssLabel" key="manager.setup.lockssEnable" lockssUrl=$lockssUrl}
			{fbvElement type="checkbox" id="enableLockss" value="1" checked=$enableLockss label=$enableLockssLabel translate=false}
		{/fbvFormSection}

		{fbvFormSection label="manager.setup.lockssLicenses"}
			{fbvElement type="textarea" multilingual=true id="lockssLicense" value=$lockssLicense rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="archivingClockss" class="border"}
		{fbvFormSection description="manager.setup.clockssDescription"}
		{/fbvFormSection}

		{fbvFormSection list="true" description="manager.setup.clockssRegister"}
			{url|assign:"clockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="clockss"}
			{translate|assign:"enableClockssLabel" key="manager.setup.clockssEnable" clockssUrl=$clockssUrl}
			{fbvElement type="checkbox" id="enableClockss" value="1" checked=$enableClockss label=$enableClockssLabel translate=false}
		{/fbvFormSection}

		{fbvFormSection label="manager.setup.clockssLicenses"}
			{fbvElement type="textarea" multilingual=true id="clockssLicense" value=$clockssLicense rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons id="archivingFormSubmit" submitText="common.save" hideCancel=true}
</form>
