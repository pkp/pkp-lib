{**
 * templates/controllers/grid/settings/user/form/userRoleContextForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form to select a context before assigning user groups to a user
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userRoleContextForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="userRoleContextForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="selectRoleContext"}">
	{csrf}

		<input type="hidden" id="userId" name="userId" value="{$userId|escape}" />

		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="contexts-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('contexts-{$uuid}', 'SelectListPanel', {$selectContextListData});
				</script>
			</div>
		{/fbvFormSection}

		{fbvFormButtons submitText="common.save"}
</form>
