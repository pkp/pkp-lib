{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to read/create/edit NavigationMenus.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler for title and area fields
		$('#navigationMenuForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		// Initialize Vue component for the navigation menu editor
		if (typeof pkp !== 'undefined' && pkp.registry) {ldelim}
			pkp.registry.initVueFromAttributes();
		{rdelim}
	{rdelim});
</script>

<form class="pkp_form" id="navigationMenuForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="updateNavigationMenu"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuFormNotification"}
	{fbvFormArea id="navigationMenuInfo"}
		{if $navigationMenuId}
			<input type="hidden" name="navigationMenuId" value="{$navigationMenuId|escape}" />
		{/if}
		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}
		{fbvFormSection title="manager.navigationMenus.form.navigationMenuArea" for="areaName"}
			{fbvElement type="select" id="areaName" from=$activeThemeNavigationAreas selected=$navigationMenuArea label="manager.navigationMenus.form.navigationMenuAreaMessage" translate=false}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormArea id="navigationMenuItems"}
		{* Vue-based Navigation Menu Editor *}
		<div
			id="navigationMenuEditorRoot"
			data-vue-root
		>
			<navigation-menu-editor-panel
				navigation-menu-id="{$navigationMenuId|escape}"
				api-url="{url router=PKP\core\PKPApplication::ROUTE_API context=$currentContext->getPath() endpoint="navigationMenus"}"
			></navigation-menu-editor-panel>
		</div>
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons id="navigationMenuFormSubmit" submitText="common.save"}
</form>
