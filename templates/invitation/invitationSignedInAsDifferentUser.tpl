{**
 * templates/invitation/invitationSignedInAsDifferentUser.tpl
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Landing page shown when someone other than the invited user is signed in.
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
    <div class="page page_invitation_signed_in_as_different_user">
        <h1>
            {translate key="invitation.signedInAsDifferentUser.title"}
        </h1>
        <p>
            {translate key="invitation.signedInAsDifferentUser.description"}
        </p>
        <a href="{$signOutUrl}" class="pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover bg-secondary py-[0.4375rem] px-3 border rounded">
            {translate key="invitation.signedInAsDifferentUser.signOutAndContinue"}
        </a>
    </div>
{/block}
