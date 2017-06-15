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
				'$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler',
                {ldelim}
                    fetchNavigationMenuItemsUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT op="getNavigationMenuItemsWithNoAssocId" escape=false},
                    navigationMenuItemId: '{$navigationMenuItemId}',
                    parentNavigationMenuItemId: '{$parentNavigationMenuItemId}',
                {rdelim});
        {rdelim});

	{*$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuItemForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});*}
</script>

<form class="pkp_form" id="navigationMenuItemForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" navigationMenuIdParent=$navigationMenuIdParent op="updateNavigationMenuItem"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuItemFormNotification"}
	{fbvFormArea id="navigationMenuItemInfo"}
        {if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}
        {fbvFormSection list="false"}
	        {fbvElement type="checkbox" id="enabled" value=1 label="manager.navigationMenus.form.menuItemEnabled" checked=$navigationMenuItemEnabled}
        {/fbvFormSection}

        {if $navigationMenus}
            {if $navigationMenuId != 0}
			    {fbvElement type="select" id="navigationMenuId" required="true" from=$navigationMenus selected=$navigationMenuId label="manager.navigationMenus.form.parentNavigationMenu" translate=false}
            {else}
                {fbvElement type="select" id="navigationMenuId" required="true" from=$navigationMenus selected=$navigationMenuIdParent label="manager.navigationMenus.form.parentNavigationMenu" translate=false}
            {/if}
		{/if}
		{if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}
        
        <div id="possibleParentNavigationMemuItemsDiv"></div>

		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}
        {fbvFormSection title="manager.navigationMenus.form.path" for="title" required="true"}
			{fbvElement type="text" id="path" value=$path maxlength="255" required="true"}
		{/fbvFormSection}
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons id="navigationMenuItemFormSubmit" submitText="common.save"}
</form>
