{**
 * templates/frontend/components/highlights.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display a list of highlights
 *
 * @uses $highlights LazyCollection List of highlights
 *}

<div class="highlights">
    <h2 class="pkp_screen_reader">{translate key="common.highlights"}</h2>
    <div class="swiper">
        <ol class="swiper-wrapper">
            {foreach from=$highlights item=highlight}
                <li class="swiper-slide {if $highlight->getImage()}-has-image{/if}">
                    {if $highlight->getImage()}
                        <img
                            class="swiper-slide-image"
                            src="{$highlight->getImageUrl()}"
                            alt="{$highlight->getImageAltText()|escape}"
                        >
                    {/if}
                    <div class="swiper-slide-content">
                        <h3 class="swiper-slide-title">
                            {$highlight->getLocalizedTitle()|strip_unsafe_html}
                        </h3>
                        <div class="swiper-slide-desc">
                            {$highlight->getLocalizedDescription()|strip_unsafe_html}
                        </div>
                        <a class="swiper-slide-button pkp_button" href="{$highlight->getUrl()|escape}">
                            {$highlight->getLocalizedUrlText()|strip_unsafe_html}
                        </a>
                    </div>
                </li>
            {/foreach}
        </ol>
        <div class="swiper-pagination"></div>
        <button class="swiper-button-prev"></button>
        <button class="swiper-button-next"></button>
    </div>
</div>
