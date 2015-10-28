{**
 * templates/frontend/pages/announcements.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the page to view the latest announcements
 *
 * @uses $announcements array List of announcements
 *}
{include file="common/frontend/header.tpl" pageTitle="announcement.announcements"}

<div class="page page_announcements">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="announcement.announcements"}

	<ul class="announcements">
		{foreach from=$announcements item=announcement}
			<li>
				{include file="frontend/objects/announcement_summary.tpl"}
			</li>
		{/foreach}
	</ul>

</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
