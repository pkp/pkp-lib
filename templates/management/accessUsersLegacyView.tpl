{**
 * controllers/tab/settings/accessUsersLegacyView.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The users grid
 *}

{* Help Link *}
{help file="users-and-roles" class="pkp_help_tab"}

{capture assign=usersUrl}{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="fetchGrid" oldUserId=$oldUserId escape=false}{/capture}
{load_url_in_div id="userGridContainer" url=$usersUrl}
