{**
 * controllers/tab/settings/accessUsers.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief The users grid
 *}

{* Help Link *}
{help file="users-and-roles" class="pkp_help_tab"}

{capture assign=usersUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="fetchGrid" oldUserId=$oldUserId escape=false}{/capture}
{load_url_in_div id="userGridContainer" url=$usersUrl}
