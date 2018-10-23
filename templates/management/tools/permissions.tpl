{**
 * templates/management/tools/permissions.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the permissions tool page.
 *
 *}
<div class="pkp_page_content pkp_page_permissions">
	{help file="tools" class="pkp_help_tab"}

	<h3>{translate key="manager.setup.resetPermissions"}</h3>
	<p>{translate key="manager.setup.resetPermissions.description"}</p>

	<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#resetPermissionsForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler',
			{ldelim}
				confirmText: {translate|json_encode key="manager.setup.resetPermissions.confirm"},
			{rdelim}
		);
	{rdelim});
	</script>

	<form class="pkp_form" id="resetPermissionsForm" method="post" action="{url router=$smarty.const.ROUTE_PAGE page="management" op="tools" path="resetPermissions"}">
		{csrf}
		{fbvElement type="submit" id="resetPermissionsFormButton" label="manager.setup.resetPermissions"}
	</form>
</div>
