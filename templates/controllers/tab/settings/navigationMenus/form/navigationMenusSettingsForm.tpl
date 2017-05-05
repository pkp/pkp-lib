{**
 * controllers/tab/settings/announcements/form/announcementSettingsForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Announcement settings form.
 *
 *}

{* Help Link *}
{help file="settings.md" section="website" class="pkp_help_tab"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
	    $('#navigationMenusSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form id="navigationMenusSettingsForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="navigationMenus"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="navigationMenusSettingsFormNotification"}

	{url|assign:navigationMenusGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenusGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="navigationMenuGridContainer" url=$navigationMenusGridUrl}

    {url|assign:navigationMenuItemsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.navigationMenus.NavigationMenuItemsGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="navigationMenuItemsGridContainer" url=$navigationMenuItemsGridUrl}

	{*{fbvFormButtons id="navigationMenusSettingsFormSubmit" submitText="common.save" hideCancel=true}*}
</form>
