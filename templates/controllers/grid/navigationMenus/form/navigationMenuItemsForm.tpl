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
                previewUrl: {url|json_encode router=$smarty.const.ROUTE_PAGE page="navigationMenu" op="preview"}
            {rdelim});
    {rdelim});
</script>

<form class="pkp_form" id="navigationMenuItemForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="updateNavigationMenuItem"}">
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

        {fbvFormSection title="manager.navigationMenus.form.navigationMenuItemType" for="area_name"}
			{fbvElement type="select" id="type" from=$navigationMenuTypes selected=$navigationMenuItemType label="manager.navigationMenus.form.navigationMenuItemTypeMessage" translate=false}
		{/fbvFormSection}
        
        {fbvFormSection id="customItemFields"}
            {fbvFormSection title="manager.navigationMenus.form.chooseTarget" for="useCustomUrl" list=true}
		        {if $useCustomUrl}
			        {assign var="checked" value=true}
		        {else}
			        {assign var="checked" value=false}
		        {/if}
		        {fbvElement type="checkbox" name="useCustomUrl" id="useCustomUrl" checked=$checked label="manager.navigationMenus.form.urlDescription" translate="true"}
            {/fbvFormSection}
            {fbvFormSection id="targetUrl" title="manager.navigationMenus.form.url" for="customUrl" list=true required="true"}
                {fbvElement type="text" id="customUrl" value=$customUrl maxlength="255" required="true"}
		    {/fbvFormSection}
            {fbvFormSection id="targetPath"}
                {fbvFormSection title="manager.navigationMenus.form.path" for="path" required="true"}
			        {fbvElement type="text" id="path" value=$path maxlength="255" required="true"}
		        {/fbvFormSection}
                {fbvFormSection}
			        {url|replace:"REPLACEME":"%PATH%"|assign:"exampleUrl" router=$smarty.const.ROUTE_PAGE context=$currentContext->getPath() page="REPLACEME"}
			        {translate key="manager.navigationMenus.form.viewInstructions" pagesPath=$exampleUrl}
		        {/fbvFormSection}
		        {fbvFormSection label="manager.navigationMenus.form.content" for="content"}
			        {fbvElement type="textarea" multilingual=true name="content" id="content" value=$content rich=true height=$fbvStyles.height.TALL variables=$allowedVariables}
		        {/fbvFormSection}
            {/fbvFormSection}
        {/fbvFormSection}
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{*{fbvFormButtons id="navigationMenuItemFormSubmit" submitText="common.save"}*}
    {fbvFormSection class="formButtons"}
        {fbvElement type="submit" class="submitFormButton pkp_helpers_align_left pkp_button_primary" id=$buttonId label="common.save"}
		{assign var=buttonId value="submitFormButton"|concat:"-"|uniqid}
		{fbvElement type="button" class="pkp_button_link" id="previewButton" label="common.preview"}
	{/fbvFormSection}
</form>
