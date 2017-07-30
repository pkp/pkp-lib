{**
 * templates/controllers/grid/navigationMenus/form/navigationMenuItemAssignmentsForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to read/edit navigation menu Item Assignments.
 *}

<script>
    $(function() {ldelim}
		// Attach the form handler.
        $('#navigationMenuItemAssignmentForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="navigationMenuItemAssignmentForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="updateNavigationMenuItemAssignment"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuItemAssignmentFormNotification"}
	{fbvFormArea id="navigationMenuItemAssignmentInfo"}
        {if $navigationMenuItemId}
			<input type="hidden" name="navigationMenuItemAssignmentId" value="{$navigationMenuItemAssignmentId|escape}" />
		{/if}

		{fbvFormSection title="manager.navigationMenus.form.title" for="title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" required="true"}
		{/fbvFormSection}
     
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

    {fbvFormButtons id="navigationMenuItemAssignmentFormSubmit" submitText="common.save"}
</form>
