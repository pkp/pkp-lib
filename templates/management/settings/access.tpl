{**
 * templates/management/settings/access.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Access and Security page.
 *}

{strip}
{assign var="pageTitle" value="navigation.access"}
{include file="common/header.tpl"}
{/strip}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#accessTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="accessTabs" class="pkp_controllers_tab">
	<ul>
		<li><a name="users" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.AccessSettingsTabHandler" op="showTab" tab="users"}">{translate key="manager.users"}</a></li>
		{if array_intersect(array(ROLE_ID_MANAGER), (array)$userRoles)}
			<li><a name="roles" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.AccessSettingsTabHandler" op="showTab" tab="roles"}">{translate key="manager.roles"}</a></li>
			<li><a name="siteAccessOptions" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.AccessSettingsTabHandler" op="showTab" tab="siteAccessOptions"}">{translate key="manager.siteAccessOptions.siteAccessOptions"}</a></li>
		{/if}
		{call_hook name="Templates::Management::Settings::access"}
	</ul>
</div>

{include file="common/footer.tpl"}
