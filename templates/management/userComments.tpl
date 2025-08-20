{**
 * templates/management/userComments.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Manage user comments
 *
 * @hook Template::UserComments []
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <user-comments-page v-bind="pageInitConfig"/>
    {call_hook name="Template::UserComments"}
{/block}
