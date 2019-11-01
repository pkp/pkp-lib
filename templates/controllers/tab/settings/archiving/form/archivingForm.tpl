{**
 * controllers/tab/settings/archiving/form/archivingForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Archiving settings form.
 *
 *}

{* Help Link *}
{help file="settings.md" section="website" class="pkp_help_tab"}

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
			{fbvFormArea title="manager.setup.plnPluginArchiving" id="plnArea"}
				{translate key="manager.setup.plnDescription"}             

				{fbvFormSection list="true" translate=false}
					{capture assign="enablePLNArchivingLabel"}{translate key="manager.setup.plnPluginEnable"}{/capture}
					{fbvElement type="checkbox" id="enablePln" value="1" checked=$isPLNPluginEnabled label=$enablePLNArchivingLabel translate=false}
				{/fbvFormSection}
			{/fbvFormArea}
			{if $isPLNPluginEnabled}
				{fbvFormSection translate=false}
					{translate key="manager.setup.plnSettingsDescription"}

					<div id="pln-settings-action" class="pkp_linkActions">
						{include file="linkAction/linkAction.tpl" action=$plnSettingsShowAction contextId="archivingForm"}
					</div>
				{/fbvFormSection}

				{fbvFormSection translate=false}
					{capture assign=depositsGridUrl}{url component="plugins.generic.pln.controllers.grid.PLNStatusGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="depositsGridContainer"|uniqid url=$depositsGridUrl}
				{/fbvFormSection}
			{/if}
		{/fbvFormArea}
		<p class="expand-others">
			<a id="toggleOthers" href="#">{translate key="manager.setup.otherLockss"}</a>
		</p>
	{else}
		{fbvFormArea title="manager.setup.plnPluginArchiving" id="plnPluginArchivingArea"}
			{translate key="manager.setup.plnPluginNotInstalled"}
		{/fbvFormArea}
	{/if}
	
	{fbvFormArea id="otherLockss"}
		{fbvFormArea title="manager.setup.lockssTitle" id="lockss_description"}
			{translate key="manager.setup.lockssDescription"}
			
			{fbvFormSection list="true" translate=false}
				{capture assign="lockssUrl"}{url router=$smarty.const.ROUTE_PAGE page="gateway" op="lockss"}{/capture}
				{capture assign="enableLockssLabel"}{translate key="manager.setup.lockssEnable" lockssUrl=$lockssUrl}{/capture}
				{fbvElement type="checkbox" id="enableLockss" value="1" checked=$enableLockss label=$enableLockssLabel translate=false}
			{/fbvFormSection}
		{/fbvFormArea}

		{fbvFormArea title="manager.setup.clockssTitle" id="clockss_description"}
			{translate key="manager.setup.clockssDescription"}
			
			{fbvFormSection list="true" translate=false}
				{capture assign="clockssUrl"}{url router=$smarty.const.ROUTE_PAGE page="gateway" op="clockss"}{/capture}
				{capture assign="enableClockssLabel"}{translate key="manager.setup.clockssEnable" clockssUrl=$clockssUrl}{/capture}
				{fbvElement type="checkbox" id="enableClockss" value="1" checked=$enableClockss label=$enableClockssLabel translate=false}
			{/fbvFormSection}
		{/fbvFormArea}

		{if $isPorticoPluginInstalled}
			{fbvFormArea title="manager.setup.porticoTitle" id="portico_description"}
				{translate key="manager.setup.porticoDescription"}

				{fbvFormSection list="true" translate=false}
					{capture assign="enablePorticoLabel"}{translate key="manager.setup.porticoEnable"}{/capture}
					{fbvElement type="checkbox" id="enablePortico" value="1" checked=$enablePortico label=$enablePorticoLabel translate=false}
				{/fbvFormSection}
			{/fbvFormArea}
		{/if}
	{/fbvFormArea}

	{fbvFormButtons id="archivingFormSubmit" submitText="common.save" hideCancel=true}
</form>
