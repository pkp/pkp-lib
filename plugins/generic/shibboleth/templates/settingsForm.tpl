{**
 * plugins/generic/shibboleth/templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Google Analytics plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#shibSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="shibSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="shibSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.shibboleth.manager.settings.description"}</div>

	{fbvFormArea id="shibbolethSettingsFormArea"}
		{fbvElement type="text" name="shibbolethWayfUrl" value=$shibbolethWayfUrl label="plugins.generic.shibboleth.manager.settings.shibbolethWayfUrl"}
		{fbvElement type="text" name="shibbolethHeaderUin" value=$shibbolethHeaderUin label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderUin"}
		{fbvElement type="text" name="shibbolethHeaderFirstName" value=$shibbolethHeaderFirstName label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderFirstName"}
		{fbvElement type="text" name="shibbolethHeaderLastName" value=$shibbolethHeaderLastName label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderLastName"}
		{fbvElement type="text" name="shibbolethHeaderInitials" value=$shibbolethHeaderInitials label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderInitials"}
		{fbvElement type="text" name="shibbolethHeaderEmail" value=$shibbolethHeaderEmail label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderEmail"}
		{fbvElement type="text" name="shibbolethHeaderPhone" value=$shibbolethHeaderPhone label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderPhone"}
		{fbvElement type="text" name="shibbolethHeaderMailing" value=$shibbolethHeaderMailing label="plugins.generic.shibboleth.manager.settings.shibbolethHeaderMailing"}
		{fbvElement type="text" name="shibboletAdminUins" value=$shibbolethAdminUins label="plugins.generic.shibboleth.manager.settings.shibbolethAdminUins"}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
