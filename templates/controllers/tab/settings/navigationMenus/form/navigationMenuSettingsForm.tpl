{**
 * controllers/tab/settings/navigationMenus/form/NavigationMenuSettingsForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * NavigationMenus settings form.
 *
 *}

{* Help Link *}
{help file="settings.md" section="website" class="pkp_help_tab"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#navigationMenuSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form id="navigationMenuSettingsForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="navigationMenus"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenuSettingsFormNotification"}

	{url|assign:navigationMenusGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="navigationMenuGridContainer" url=$navigationMenusGridUrl}

	{url|assign:navigationMenuItemsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="navigationMenuItemsGridContainer" url=$navigationMenuItemsGridUrl}

</form>
