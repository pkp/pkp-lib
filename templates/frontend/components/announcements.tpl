{**
 * templates/frontend/components/announcements.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display a list of announcements
 *
 * @uses $announcements array List of announcements
 *}

<ul class="cmp_announcements">
	{foreach from=$announcements item=announcement}
		<li>
			{include file="frontend/objects/announcement_summary.tpl"}
		</li>
	{/foreach}
</ul>
