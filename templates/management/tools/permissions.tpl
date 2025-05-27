{**
 * templates/management/tools/permissions.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display the permissions tool page.
 *
 *}
<script>
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

<form class="pkp_form" id="resetPermissionsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="management" op="tools" path="resetPermissions"}">
	<div class="pkp_page_content pkp_page_permissions">
		<h3 class="mb-2">{translate key="manager.setup.resetPermissions"}</h3>
		<p class="mb-2">{translate key="manager.setup.resetPermissions.description"}</p>

		{csrf}
		{fbvElement type="submit" id="resetPermissionsFormButton" label="manager.setup.resetPermissions"}
	</div>
</form>
