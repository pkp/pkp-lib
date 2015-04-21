{**
 * templates/user/rolesForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Roles area of user profile form tabset.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#rolesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="rolesForm" method="post" action="{url op="saveRoles"}" enctype="multipart/form-data">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="rolesFormNotification"}

	{include file="user/userGroups.tpl"}

	{fbvFormSection for="interests"}
		{fbvElement type="interests" id="interests" interests=$interests label="user.interests"}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
