{**
 * templates/frontend/pages/announcements.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the page to view the latest announcements
 *
 * @uses $announcements array List of announcements
 *}
{include file="frontend/components/header.tpl" pageTitle="announcement.announcements"}

<div class="page page_announcements">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="announcement.announcements"}
	<h1 class="pageCurrentTitle">
		{translate key="announcement.announcements"}
	</h1>
	{include file="frontend/components/editLink.tpl" page="management" op="settings" path="website" anchor="announcements" sectionTitleKey="announcement.announcements"}

	{$announcementsIntroduction}

	{include file="frontend/components/announcements.tpl"}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
