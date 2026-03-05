{**
 * templates/invitation/acceptInvitation.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief show decline confirmation invitation page to the users.
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
    <div class="page page_invitation_decline">
        <h1>
            {translate key="invitation.decline.confirm.title"}
        </h1>
        <p>
            {translate key="invitation.decline.confirm.description"}
        </p>
        <form method="post" action="{$declineUrl}">
            {csrf}
            <button type="submit" class="pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover bg-secondary py-[0.4375rem] px-3 border rounded">
                {translate key="invitation.decline.confirm"}
            </button>
        </form>
    </div>
{/block}
