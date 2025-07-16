{**
 * templates/management/userComments.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display page for moderating user comments.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <div class="app__contentPanel overflow-auto"/>
        <user-comments-page v-bind="pageInitConfig"/>
    </div>
{/block}
