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
		$('#navigationMenuForm').pkpHandler('$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler',);
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
			{fbvElement type="text" id="title" readonly=$navigationMenuIsDefault value=$title maxlength="255" required="true"}
		{/fbvFormSection}
        {fbvFormSection title="manager.navigationMenus.form.navigationMenuArea" for="area_name"}
            {fbvElement type="select" id="area_name" from=$activeThemeNavigationAreas selected=$navigationMenuArea label="manager.navigationMenus.form.navigationMenuAreaMessage" translate=false}
        {/fbvFormSection}
	{/fbvFormArea}
	{fbvFormArea id="navigationMenuItems"}
		<div id="pkpNavManagement" class="pkp_nav_management">
			<div class="pkp_nav_assigned">
				<div class="pkp_nav_management_header">
					{translate key="manager.navigationMenus.assignedMenuItems"}
				</div>
				<ul id="pkpNavAssigned">
					{foreach from=$menuTree item="assignment"}
						<li data-id="{$assignment->getMenuItemId()|escape}">
							<div class="item">
								<span class="fa fa-sort"></span>
								{$assignment->navigationMenuItem->getLocalizedTitle()}
							</div>
							{if !empty($assignment->children)}
								<ul>
									{foreach from=$assignment->children item="childAssignment"}
										<li data-id="{$childAssignment->getMenuItemId()|escape}">
											<div class="item">
												<span class="fa fa-sort"></span>
												{$childAssignment->navigationMenuItem->getLocalizedTitle()}
											</div>
										</li>
									{/foreach}
								</ul>
							{/if}
						</li>
					{/foreach}
				</ul>
			</div>
			<div class="pkp_nav_unassigned">
				<div class="pkp_nav_management_header">
					{translate key="manager.navigationMenus.unassignedMenuItems"}
				</div>
				<ul id="pkpNavUnassigned">
					{foreach from=$unassignedItems item="unassignedItem"}
						<li data-id="{$unassignedItem->getId()|escape}">
							<div class="item">
								<span class="fa fa-sort"></span>
								{$unassignedItem->getLocalizedTitle()}
							</div>
						</li>
					{/foreach}
				</ul>
			</div>
			{foreach from=$menuTree item="assignment"}
				<input type="hidden" name="menuTree[{$assignment->getMenuItemId()|escape}][seq]" value="{$assignment->getSequence()|escape}">
				{foreach from=$assignment->children item="childAssignment"}
					<input type="hidden" name="menuTree[{$childAssignment->getMenuItemId()|escape}][seq]" value="{$childAssignment->getSequence()|escape}">
					<input type="hidden" name="menuTree[{$childAssignment->getMenuItemId()|escape}][parentId]" value="{$childAssignment->getParentId()|escape}">
				{/foreach}
			{/foreach}
		</div>
	{/fbvFormArea}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons id="navigationMenuFormSubmit" submitText="common.save"}
</form>
