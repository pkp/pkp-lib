{**
 * templates/controllers/page/tasks.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User-specific tasks panel.
 *}
{capture assign=notificationsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.TaskNotificationsGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="notificationsGrid" url=$notificationsGridUrl}
