{**
 * templates/frontend/objects/announcement_summary.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display a summary view of an announcement
 *
 * @uses $announcement Announcement The announcement to display
 * @uses $heading string HTML heading element, default: h2
 *}
{if !$heading}
	{assign var="heading" value="h2"}
{/if}

<article class="obj_announcement_summary{if $announcement->image} obj_announcement_summary_has_image{/if}">
	{if $announcement->image}
		<img
			class="obj_announcement_summary_image"
			src="{$announcement->imageUrl}"
			alt="{$announcement->imageAltText}"
		/>
	{/if}
	<div class="obj_announcement_summary_details">
		<{$heading}>
			<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="announcement" op="view" path=$announcement->id}">
				{$announcement->getLocalizedData('title')|escape}
			</a>
		</{$heading}>
		<div class="date">
			{$announcement->datePosted->format($dateFormatShort)}
		</div>
		<div class="summary">
			{$announcement->getLocalizedData('descriptionShort')|strip_unsafe_html}
			<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="announcement" op="view" path=$announcement->id}" class="read_more">
				<span aria-hidden="true" role="presentation">
					{translate key="common.readMore"}
				</span>
				<span class="pkp_screen_reader">
					{translate key="common.readMoreWithTitle" title=$announcement->getLocalizedData('title')|escape}
				</span>
			</a>
		</div>
	</div>
</article><!-- .obj_announcement_summary -->
