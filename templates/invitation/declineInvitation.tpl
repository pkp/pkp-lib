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
        <pkp-button
            element="a"
            href="{$declineUrl}"
        >
            {translate key="invitation.decline.confirm"}
        </pkp-button>
    </div>
{/block}