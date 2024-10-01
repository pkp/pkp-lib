{**
 * templates/frontend/objects/announcements_list.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display a list of announcements
 *
 * @uses $numAnnouncements int The number of announcements to display in the list
 * @uses $announcements Announcement[] The list of announcements
 *}

{if $numAnnouncements && $announcements|@count}
    <section class="cmp_announcements highlight_first">
        <a id="homepageAnnouncements"></a>
        <h2>
            {translate key="announcement.announcements"}
        </h2>
        {foreach name=announcements from=$announcements item=announcement}
            {if $smarty.foreach.announcements.iteration > $numAnnouncements}
                {break}
            {/if}
            {if $smarty.foreach.announcements.iteration == 1}
                {include file="frontend/objects/announcement_summary.tpl" heading="h3"}
                <div class="more">
            {else}
                <article class="obj_announcement_summary">
                    <h4>
                        <a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="announcement" op="view" path=$announcement->id}">
                            {$announcement->getLocalizedData('title')|escape}
                        </a>
                    </h4>
                    <div class="date">
                        {$announcement->datePosted->format($dateFormatShort)}
                    </div>
                </article>
            {/if}
        {/foreach}
        </div><!-- .more -->
    </section>
{/if}
