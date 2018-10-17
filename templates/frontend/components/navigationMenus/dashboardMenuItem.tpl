{**
 * templates/frontend/components/navigationMenus/dashboardMenuItem.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Dashboard menuItem Template
 *
 *}

{$navigationMenuItem->getLocalizedTitle()|escape}
<span class="task_count">
	{$unreadNotificationCount}
</span>
