{**
 * templates/user/notificationSettingsForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#notificationSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="notificationSettingsForm" method="post" action="{url op="saveIdentity"}" enctype="multipart/form-data">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="notificationSettingsFormNotification"}

	{$additionalNotificationSettingsContent}

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
