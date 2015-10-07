{**
 * templates/frontend/objects/announcement_full.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the full view of an announcement, when the announcement is
 *  the primary element on the page.
 *
 * @uses $announcement Announcement The announcement to display
 *}

<article class="obj_announcement_full">
	<h1>
		{$announcement->getLocalizedTitle()}
	</h1>
	<div class="date">
		{$announcement->getDatePosted()}
	</div>
	<div class="description">
		{if $announcement->getLocalizedDescription()}
			{$announcement->getLocalizedDescription()|strip_unsafe_html}
		{else}
			{$announcement->getLocalizedDescriptionShort()|strip_unsafe_html}
		{/if}
	</div>
	{if $announcement->getDateExpire() != ''}
		<span class="expire">
			{$announcement->getDateExpire()}
		</span>
	{/if}
</article><!-- .obj_announcement_full -->
