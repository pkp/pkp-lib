{**
 * templates/frontend/pages/announcements.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display the page which represents a single announcement
 *
 * @uses $announcement Announcement The announcement to display
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$announcement->getLocalizedTitle()|escape}

<div class="page page_announcement">

	{include file="frontend/components/breadcrumbs_announcement.tpl" currentTitle=$announcement->getLocalizedTitle()}

	{* Display book details *}
	{include file="frontend/objects/announcement_full.tpl"}

</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
