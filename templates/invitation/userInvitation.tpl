?php
{**
 * templates/management/userInvitation.tpl
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief show create user invitation page to the users.
 *
 * @hook Template::Settings::access []
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
    <user-invitation-page
            :primary-locale="primaryLocale"
            :email-templates-api-url="emailTemplatesApiUrl"
            :page-title="pageTitle"
            :page-title-description="pageTitleDescription"
            :invitation-payload="invitationPayload"
            :invitation-type="invitationType"
            :invitation-mode="invitationMode"
            :steps="steps"
    />
{/block}
