{**
 * controllers/tab/settings/users.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User management.
 *
 *}

{url|assign:usersUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="fetchGrid" oldUserId=$oldUserId escape=false}
{assign var=gridContainerId value="userGridContainer"}
{load_url_in_div id=$gridContainerId url=$usersUrl}
