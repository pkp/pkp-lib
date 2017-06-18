{**
 * controllers/grid/navigationMenus/form/navigationMenuItemsManagementForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for configuring navigation menu items of a navigationMenu
 *
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuItemsManagementForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="navigationMenuItemsManagementForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="listbuilder.navigationMenus.NavigationMenuItemsListbuilderHandler" op="updateNavigationMenuItems"}">
    {csrf}
    <input type="hidden" name="navigationMenuIdParent" value="{$navigationMenuIdParent|escape}" />

    {url|assign:navigationMenuItemsGridUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.navigationMenus.NavigationMenuItemsListbuilderHandler" navigationMenuIdParent=$navigationMenuIdParent op="fetch" escape=false}
    {load_url_in_div id="navigationMenuItemsManagementGridContainer" url=$navigationMenuItemsGridUrl}

    {fbvFormButtons id="navigationMenuItemsManagementFormSubmit" submitText="common.save"}
</form>

{*{url|assign:navigationMenuItemsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" navigationMenuIdParent=$navigationMenuIdParent op="fetchGrid" escape=false}
{load_url_in_div id="navigationMenuItemsGridContainer" url=$navigationMenuItemsGridUrl}*}

