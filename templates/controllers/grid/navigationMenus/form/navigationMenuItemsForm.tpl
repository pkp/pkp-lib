{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to read/create/edit navigation menu Items.
 *}

<script>
    $(function() {ldelim}
		// Attach the form handler.
        $('#navigationMenuItemForm').pkpHandler(
				'$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler');
    {rdelim});

	{*$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuItemForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});*}
</script>

<form class="pkp_form" id="navigationMenuItemForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="updateNavigationMenuItem"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuItemFormNotification"}
	{fbvFormArea id="navigationMenuItemInfo"}
        {if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}
        {if $navigationMenus}
			{fbvElement type="select" id="navigationMenuId" required="true" from=$navigationMenus selected=$navigationMenuId label="manager.navigationMenus.form.navigationMenuTitle" translate=false}
		{/if}
		{if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}
		{fbvFormSection title="manager.navigationMenus.form.typeName" for="title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}
        {fbvFormSection title="manager.navigationMenus.form.menuItemPath" for="title" required="true"}
			{fbvElement type="text" id="path" value=$path maxlength="255" required="true"}
		{/fbvFormSection}
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons id="navigationMenuItemFormSubmit" submitText="common.save"}
</form>
