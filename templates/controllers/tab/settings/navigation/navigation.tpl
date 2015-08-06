{**
 * templates/controllers/tab/content/navigation/navigation.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Navigation management.
 *
 * Note that there are two grids loaded on this template.
 *}

<div class="pkp_settings_navigation">
	{url|assign:footerGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.content.navigation.ManageFooterGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="footerGridContainer" url=$footerGridUrl}
	{url|assign:socialGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.content.navigation.ManageSocialMediaGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="socialMediaGridContainer" url=$socialGridUrl}
</div>
