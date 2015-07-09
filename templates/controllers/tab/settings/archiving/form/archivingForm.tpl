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

	{fbvFormArea id="archivingLockss" title="manager.setup.lockssTitle"}
		<div class="description">
			{translate key="manager.setup.lockssDescription"}
		</div>
		<div class="description">
			{url|assign:"lockssExistingArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_EXISTING_ARCHIVE"}
			{url|assign:"lockssNewArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_NEW_ARCHIVE"}
			{translate key="manager.setup.lockssRegister" lockssExistingArchiveUrl=$lockssExistingArchiveUrl lockssNewArchiveUrl=$lockssNewArchiveUrl}
		</div>

		{fbvFormSection list="true" translate=false}
			{url|assign:"lockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="lockss"}
			{translate|assign:"enableLockssLabel" key="manager.setup.lockssEnable" lockssUrl=$lockssUrl}
			{fbvElement type="checkbox" id="enableLockss" value="1" checked=$enableLockss label=$enableLockssLabel translate=false}
		{/fbvFormSection}

		{fbvFormSection for="lockssLicense" label="manager.setup.lockssLicenseLabel" description="manager.setup.lockssLicenseDescription"}
			{fbvElement type="textarea" multilingual=true id="lockssLicense" value=$lockssLicense rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="archivingClockss" title="manager.setup.clockssTitle"}
		<div class="description">
			{translate key="manager.setup.clockssDescription"}
		</div>
		<div class="description">
			{translate key="manager.setup.clockssRegister"}
		</div>

		{fbvFormSection list="true"}
			{url|assign:"clockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="clockss"}
			{translate|assign:"enableClockssLabel" key="manager.setup.clockssEnable" clockssUrl=$clockssUrl}
			{fbvElement type="checkbox" id="enableClockss" value="1" checked=$enableClockss label=$enableClockssLabel translate=false}
		{/fbvFormSection}

		{fbvFormSection for="clockssLicense" label="manager.setup.clockssLicenseLabel" description="manager.setup.clockssLicenseDescription"}
			{fbvElement type="textarea" multilingual=true id="clockssLicense" value=$clockssLicense rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons id="archivingFormSubmit" submitText="common.save" hideCancel=true}
</form>
