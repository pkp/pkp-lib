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
		// Attach the form handler.
		$('#navigationMenuForm').pkpHandler('$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler',
			{ldelim}
				submenuWarning: {translate|json_encode key="manager.navigationMenus.form.submenuWarning"},
				itemTypeConditionalWarnings: {$navigationMenuItemTypeConditionalWarnings},
				okButton: {translate|json_encode key="common.ok"},
				warningModalTitle: {translate|json_encode key="common.notice"}
			{rdelim}
		);

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
		{fbvFormSection title="manager.navigationMenus.form.navigationMenuArea" for="areaName"}
			{fbvElement type="select" id="areaName" from=$activeThemeNavigationAreas selected=$navigationMenuArea label="manager.navigationMenus.form.navigationMenuAreaMessage" translate=false}
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
						{assign var="itemType" value=$assignment->navigationMenuItem->getType()}
						{if !empty($navigationMenuItemTypes.$itemType.conditionalWarning)}
							{assign var="hasConditionalDisplay" value=true}
						{else}
							{assign var="hasConditionalDisplay" value=false}
						{/if}
						<li data-id="{$assignment->getMenuItemId()|escape}" data-type="{$itemType|escape}">
							<div class="item">
								<div class="item_title">
									<span class="fa fa-sort"></span>
									{$assignment->navigationMenuItem->getLocalizedTitle()|escape}
								</div>
								<div class="item_buttons">
									{if $hasConditionalDisplay}
										<button class="btnConditionalDisplay">
											<span class="fa fa-eye-slash"></span>
											<span class="-screenReader">
												{translate key="manager.navigationMenus.form.conditionalDisplay"}
											</span>
										</button>
									{/if}
								</div>
							</div>
							{if !empty($assignment->children)}
								<ul>
									{foreach from=$assignment->children item="childAssignment"}
										{assign var="itemType" value=$childAssignment->navigationMenuItem->getType()}
										{if !empty($navigationMenuItemTypes.$itemType.conditionalWarning)}
											{assign var="hasConditionalDisplay" value=true}
										{else}
											{assign var="hasConditionalDisplay" value=false}
										{/if}
										<li data-id="{$childAssignment->getMenuItemId()|escape}" data-type="{$itemType|escape}">
											<div class="item">
												<div class="item_title">
													<span class="fa fa-sort"></span>
													{$childAssignment->navigationMenuItem->getLocalizedTitle()|escape}
												</div>
												<div class="item_buttons">
													{if $hasConditionalDisplay}
														<button class="btnConditionalDisplay">
															<span class="fa fa-eye-slash"></span>
															<span class="-screenReader">
																{translate key="manager.navigationMenus.form.conditionalDisplay"}
															</span>
														</button>
													{/if}
												</div>
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
						{assign var="itemType" value=$unassignedItem->getType()}
						{if !empty($navigationMenuItemTypes.$itemType.conditionalWarning)}
							{assign var="hasConditionalDisplay" value=true}
						{else}
							{assign var="hasConditionalDisplay" value=false}
						{/if}
						<li data-id="{$unassignedItem->getId()|escape}" data-type="{$itemType|escape}">
							<div class="item">
								<div class="item_title">
									<span class="fa fa-sort"></span>
									{$unassignedItem->getLocalizedTitle()|escape}
								</div>
								<div class="item_buttons">
									{if $hasConditionalDisplay}
										<button class="btnConditionalDisplay">
											<span class="fa fa-eye-slash"></span>
											<span class="-screenReader">
												{translate key="manager.navigationMenus.form.conditionalDisplay"}
											</span>
										</button>
									{/if}
								</div>
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
