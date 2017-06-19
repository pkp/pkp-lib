{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to read/create/edit NavigationMenus.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="navigationMenuForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="updateNavigationMenu"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuFormNotification"}
	{fbvFormArea id="navigationMenuInfo"}
		{if $navigationMenuId}
			<input type="hidden" name="navigationMenuId" value="{$navigationMenuId|escape}" />
		{/if}
		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}
        {fbvFormSection title="manager.navigationMenus.form.navigationMenuArea" for="area_name"}
            {fbvElement type="select" id="area_name" from=$activeThemeNavigationAreas selected=$navigationMenuArea label="manager.navigationMenus.form.navigationMenuAreaMessage" translate=false}
        {/fbvFormSection}
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons id="navigationMenuFormSubmit" submitText="common.save"}
</form>
