{**
 * controllers/tab/settings/archiving/form/archivingForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Archiving settings form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#archivingForm').pkpHandler('$.pkp.controllers.tab.settings.archiving.form.ArchivingSettingsFormHandler',
			{ldelim}
				baseUrl: {$baseUrl|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form id="archivingForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="archiving"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="archivingFormNotification"}

	<input type="hidden" value="{$isPLNPluginInstalled|escape}" id="isPLNPluginInstalled" name="isPLNPluginInstalled" />
	<input type="hidden" value="{$isPLNPluginEnabled|escape}" id="isPLNPluginEnabled" name="isPLNPluginEnabled" />

	{if $isPLNPluginInstalled}
		{fbvFormArea id="mainLockss"}
			{fbvFormArea title="manager.setup.enablePLNArchive" id="plnArea"}
				{fbvFormSection list="true" translate=false}
					{translate|assign:"enablePLNArchivingLabel" key="manager.setup.plnPluginEnable"}
					{fbvElement type="checkbox" id="enablePln" value="1" checked=$isPLNPluginEnabled label=$enablePLNArchivingLabel translate=false}
				{/fbvFormSection}
			{/fbvFormArea}
			{if $isPLNPluginEnabled}
				{url|assign:depositsGridUrl component="plugins.generic.pln.controllers.grid.PLNStatusGridHandler" op="fetchGrid" escape=false}
				{load_url_in_div id="depositsGridContainer" url=$depositsGridUrl}
			{/if}
		{/fbvFormArea}
		<p class="expand-others">
			<a id="toggleOthers" href="#">{translate key="manager.setup.otherLockss"}</a>
		</p>
	{else}
		<span>
			{translate key="manager.setup.plnPluginNotInstalled"}
		</span>
	{/if}
	
	{fbvFormArea id="otherLockss"}
		{fbvFormArea title="manager.setup.enableArchive" id="lockssArea"}
			{fbvFormSection list="true" translate=false}
				{url|assign:"lockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="lockss"}
				{translate|assign:"enableLockssLabel" key="manager.setup.lockssEnable" lockssUrl=$lockssUrl}
				{fbvElement type="checkbox" id="enableLockss" value="1" checked=$enableLockss label=$enableLockssLabel translate=false}

				{url|assign:"clockssUrl" router=$smarty.const.ROUTE_PAGE page="gateway" op="clockss"}
				{translate|assign:"enableClockssLabel" key="manager.setup.clockssEnable" clockssUrl=$clockssUrl}
				{fbvElement type="checkbox" id="enableClockss" value="1" checked=$enableClockss label=$enableClockssLabel translate=false}
			{/fbvFormSection}
		{/fbvFormArea}

		<div class="lockss_description">
			<h3>{translate key="manager.setup.lockssTitle"}</h3>
			<p>
				{translate key="manager.setup.lockssDescription"}
			</p>
			<p>
				{url|assign:"lockssExistingArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_EXISTING_ARCHIVE"}
				{url|assign:"lockssNewArchiveUrl" router=$smarty.const.ROUTE_PAGE page="user" op="email" template="LOCKSS_NEW_ARCHIVE"}
				{translate key="manager.setup.lockssRegister" lockssExistingArchiveUrl=$lockssExistingArchiveUrl lockssNewArchiveUrl=$lockssNewArchiveUrl}
			</p>
		</div>

		<div class="clockss_description">
			<h3>{translate key="manager.setup.clockssTitle"}</h3>
			<p>
				{translate key="manager.setup.clockssDescription"}
			</p>
			<p>
				{translate key="manager.setup.clockssRegister"}
			</p>
		</div>
	{/fbvFormArea}
	{fbvFormButtons id="archivingFormSubmit" submitText="common.save" hideCancel=true}
</form>
