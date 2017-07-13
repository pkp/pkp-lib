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
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
