<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11603_AddMissingUserRoleAssignmentInvitationEmail.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11603_AddMissingUserRoleAssignmentInvitationEmail
 *
 * @brief Adds missing `USER_ROLE_ASSIGNMENT_INVITATION` email template for invitation toolset
 */


namespace PKP\migration\upgrade\v3_5_0;

use PKP\migration\upgrade\v3_5_0\InstallEmailTemplates;

class I11603_AddMissingUserRoleAssignmentInvitationEmail extends InstallEmailTemplates
{
    protected function getEmailTemplateKeys(): array
    {
        return [
            'USER_ROLE_ASSIGNMENT_INVITATION',
        ];
    }
}
