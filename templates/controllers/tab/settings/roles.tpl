{**
 * controllers/tab/settings/roles.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Page for managing user groups and stages assignments.
 *}

<div id="userGroupsContainer">
{url|assign:rolesUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.roles.UserGroupGridHandler" op="fetchGrid"}
{load_url_in_div id="roleGridContainer" url=$rolesUrl}
</div>