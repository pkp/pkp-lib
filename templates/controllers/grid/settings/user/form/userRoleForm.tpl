{**
 * templates/controllers/grid/settings/user/form/userRoleForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="userRoleFormNotification"}

	<h3>{translate key="grid.user.step2" userFullName=$userFullName}</h3>

		<input type="hidden" id="userId" name="userId" value="{$userId|escape}" />

		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="userGroups-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('userGroups-{$uuid}', 'SelectListPanel', {$selectUserListData});
				</script>
			</div>
		{/fbvFormSection}

		{fbvFormButtons submitText="common.save"}
</form>
