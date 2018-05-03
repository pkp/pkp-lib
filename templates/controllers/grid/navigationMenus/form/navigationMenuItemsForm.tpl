{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
		<input type="hidden" name="page" value="{$page|escape}" />
		<input type="hidden" name="op" value="{$op|escape}" />
		{if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemId" value="{$navigationMenuItemId|escape}" />
		{/if}

		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}

		{fbvFormSection id="menuItemTypeSection" title="manager.navigationMenus.form.navigationMenuItemType" for="menuItemType"}
			{fbvElement type="select" id="menuItemType" required=true from=$navigationMenuItemTypeTitles selected=$menuItemType label="manager.navigationMenus.form.navigationMenuItemTypeMessage" translate=false}
		{/fbvFormSection}

		{fbvFormSection id="remoteUrlTarget" title="manager.navigationMenus.form.url" for="url" list=true required="true"}
			{fbvElement type="text" id="url" value=$url maxlength="255" required="true"}
		{/fbvFormSection}

		<div id="customPageOptions">
			{fbvFormSection id="targetPath"}
				{fbvFormSection title="manager.navigationMenus.form.path" for="path" required="true"}
					{fbvElement type="text" id="path" value=$path required="true"}
					<p>
						{url|replace:"REPLACEME":"%PATH%"|assign:"exampleUrl" router=$smarty.const.ROUTE_PAGE page="REPLACEME"}
						{translate key="manager.navigationMenus.form.viewInstructions" pagesPath=$exampleUrl}
					</p>
				{/fbvFormSection}
				{fbvFormSection label="manager.navigationMenus.form.content" for="content"}
					{fbvElement type="textarea" multilingual=true name="content" id="content" value=$content rich=true height=$fbvStyles.height.TALL variables=$allowedVariables}
				{/fbvFormSection}
			{/fbvFormSection}
		</div>
	{/fbvFormArea}

	{fbvFormSection class="formButtons"}
		{fbvElement type="submit" class="submitFormButton pkp_helpers_align_left pkp_button_primary" id="saveButton" label="common.save"}
		{assign var=buttonId value="submitFormButton"|concat:"-"|uniqid}
		{fbvElement type="button" class="pkp_button_link" id="previewButton" label="common.preview"}
	{/fbvFormSection}
</form>
