{**
 * controllers/grid/settings/user/form/userRoleForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form for managing roles for a newly created user.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userRoleForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="userRoleForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="updateUserRoles"}">

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="userRoleFormNotification"}

	<h3>{translate key="grid.user.step2" userFullName=$userFullName}</h3>

		<input type="hidden" id="userId" name="userId" value="{$userId|escape}" />

		<div id="userRolesContainer" class="full left">
			{url|assign:userRolesUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.UserUserGroupListbuilderHandler" op="fetch" userId=$userId title="grid.user.addRoles" escape=false}
			{load_url_in_div id="userRolesContainer" url=$userRolesUrl}
		</div>
		{fbvFormButtons submitText="common.save"}
</form>
