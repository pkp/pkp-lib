{**
 * templates/invitation/invitationUnavailable.tpl
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Landing page shown when an invitation is no longer available (already used or expired).
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
    <div class="page page_invitation_unavailable">
        <h1>
            {translate key="invitation.unavailable.title"}
        </h1>
        <p>
            {translate key="invitation.unavailable.description"}
        </p>
        <a href="{$loginUrl}" class="pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover bg-secondary py-[0.4375rem] px-3 border rounded">
            {translate key="user.login"}
        </a>
        <a href="{$registerUrl}" class="pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover bg-secondary py-[0.4375rem] px-3 border rounded">
            {translate key="user.login.registerNewAccount"}
        </a>
    </div>
{/block}
