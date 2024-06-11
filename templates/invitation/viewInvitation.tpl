<?php
{**
 * templates/management/invitation.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The users, roles and site access settings page.
 *
 * @hook Template::Settings::access []
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
    <view-invitation-page
            :primary-locale="primaryLocale"
            :email-templates-api-url="emailTemplatesApiUrl"
            :page-title="pageTitle"
            :page-title-description="pageTitleDescription"
            :user-invitation-saved-url="userInvitationSavedUrl"
            :steps="steps"
            :user-groups="userGroups"
            :current-user-groups="currentUserGroups"
    />
{/block}
