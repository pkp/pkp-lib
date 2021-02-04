{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to read/create/edit navigation menu Items.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuItemsForm').pkpHandler(
			'$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler',
			{ldelim}
				previewUrl: {url|json_encode router=$smarty.const.ROUTE_PAGE page="navigationMenu" op="preview"},
				itemTypeDescriptions: {$navigationMenuItemTypeDescriptions},
				itemTypeConditionalWarnings: {$navigationMenuItemTypeConditionalWarnings}
			{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="navigationMenuItemsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="updateNavigationMenuItem"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuItemFormNotification"}
	{fbvFormArea id="navigationMenuItemInfo"}
		{if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}

		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}

		{fbvFormSection id="menuItemTypeSection" title="manager.navigationMenus.form.navigationMenuItemType" for="menuItemType"}
			{fbvElement type="select" id="menuItemType" required=true from=$navigationMenuItemTypeTitles selected=$menuItemType label="manager.navigationMenus.form.navigationMenuItemTypeMessage" translate=false}
		{/fbvFormSection}

		{foreach from=$customTemplates key=nmiType item=customTemplate}
			{include file=$customTemplate.template}
		{/foreach}
	{/fbvFormArea}

	{fbvFormSection class="formButtons"}
		{fbvElement type="submit" class="submitFormButton pkp_helpers_align_left pkp_button_primary" id="saveButton" label="common.save"}
		{assign var=buttonId value="submitFormButton"|concat:"-"|uniqid}
	{/fbvFormSection}
</form>
