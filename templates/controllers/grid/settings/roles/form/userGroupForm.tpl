{**
 * templates/controllers/grid/settings/roles/form/userGroupForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to edit or create a user group
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userGroupForm').pkpHandler(
			'$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler', {ldelim}
			selfRegistrationRoleIds: [{foreach from=$selfRegistrationRoleIds item=selfRegRoleId}{$selfRegRoleId|escape:'javascript'}, {/foreach}]
		{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="userGroupForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.roles.UserGroupGridHandler" op="updateUserGroup"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="userGroupFormNotification"}

	{if $userGroupId}
		<input type="hidden" id="userGroupId" name="userGroupId" value="{$userGroupId|escape}" />
	{/if}
	{fbvFormArea id="userGroupDetails"}
		<h3>{translate key="settings.roles.roleDetails"}</h3>
		{fbvFormSection title="settings.roles.from" for="roleId" required="true"}
			{fbvElement type="select" name="roleId" from=$roleOptions id="roleId" selected=$roleId disabled=$disableRoleSelect}
		{/fbvFormSection}
		{fbvFormSection title="settings.roles.roleName" for="name" required="true"}
			{fbvElement type="text" multilingual="true" name="name" value=$name id="name"}
		{/fbvFormSection}
		{fbvFormSection title="settings.roles.roleAbbrev" for="abbrev" required="true"}
			{fbvElement type="text" multilingual="true" name="abbrev" value=$abbrev id="abbrev"}
		{/fbvFormSection}
	{/fbvFormArea}
	<div id="userGroupStageContainer" class="full left">
		{fbvFormArea id="userGroupRoles"}
			{fbvFormSection title="grid.roles.stageAssignment" for="assignedStages[]" required="true" list="true"}
				{fbvElement type="checkboxgroup" name="assignedStages" id="assignedStages" from=$stages selected=$assignedStages}
			{/fbvFormSection}
			<label for="stages[]" class="error pkp_form_hidden">{translate key=settings.roles.stageIdRequired}</label>
		{/fbvFormArea}
	</div>
	<div id="userGroupOptionsContainer" class="full left">
		{fbvFormArea id="userGroupOptions"}
			{fbvFormSection title="settings.roles.roleOptions" list="true"}
				{fbvElement type="checkbox" name="showTitle" id="showTitle" checked=$showTitle label="settings.roles.showTitles"}
				{fbvElement type="checkbox" name="permitSelfRegistration" id="permitSelfRegistration" checked=$permitSelfRegistration label="settings.roles.permitSelfRegistration"}
			{/fbvFormSection}
		{/fbvFormArea}
	</div>
	{fbvFormButtons}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
