{**
 * templates/management/announcements.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Add and edit announcements and announcement types
 *}
{include file="common/header.tpl" pageTitle="manager.setup.announcements"}

{assign var="uuid" value=""|uniqid|escape}
<div id="settings-announcements-{$uuid}">
	<tabs>
		<tab id="announcements" name="{translate key="manager.setup.announcements"}">
	    {capture assign=announcementGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.ManageAnnouncementGridHandler" op="fetchGrid" escape=false}{/capture}
	    {load_url_in_div id="announcementGridContainer" url=$announcementGridUrl}
		</tab>
		<tab id="announcementTypes" name="{translate key="manager.announcementTypes"}">
	    {capture assign=announcementTypeGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.AnnouncementTypeGridHandler" op="fetchGrid" escape=false}{/capture}
	    {load_url_in_div id="announcementTypeGridContainer" url=$announcementTypeGridUrl}
		</tab>
		{call_hook name="Template::Announcements"}
	</tabs>
</div>
<script type="text/javascript">
	pkp.registry.init('settings-announcements-{$uuid}', 'SettingsContainer', {ldelim}{rdelim});
</script>

{include file="common/footer.tpl"}
