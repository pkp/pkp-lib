{**
 * templates/announcements/announcements.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Announcements description and grid.
 *}
{$announcementsIntroduction|nl2br}

{url|assign:announcementGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.announcements.ViewAnnouncementGridHandler" op="fetchGrid" displayLimit=$displayLimit escape=false}
{load_url_in_div id="announcementGridContainer" url=$announcementGridUrl}
