{**
 * templates/frontend/objects/announcement_summary.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display a summary view of an announcement
 *
 * @uses $announcement Announcement The announcement to display
 *}

<article class="obj_announcement_summary">
	<h4>
		{$announcement->getLocalizedTitle()}
	</h4>
	<div class="date">
		{$announcement->getDatePosted()}
	</div>
	<div class="summary">
		{$announcement->getLocalizedDescriptionShort()|strip_unsafe_html}
		<a href="{url router=$smarty.const.ROUTE_PAGE page="announcement" op="view" path=$announcement->getId()}" class="read_more">
			<span aria-hidden="true" role="presentation">
				{translate key="common.readMore"}
			</span>
			<span class="pkp_screen_reader">
				{translate key="common.readMoreWithTitle" title=$announcement->getLocalizedTitle()|escape}
			</span>
		</a>
	</div>
	{if $announcement->getDateExpire() != ''}
		<span class="expire">
			{$announcement->getDateExpire()}
		</span>
	{/if}
</article><!-- .obj_announcement_summary -->
