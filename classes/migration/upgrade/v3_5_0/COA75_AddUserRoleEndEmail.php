<?php

/**
 * @file classes/migration/upgrade/v3_5_0/COA75_AddUserRoleEndEmail.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class COA75_AddUserRoleEndEmail
 *
 * @brief Adds user role end email template
 */


namespace PKP\migration\upgrade\v3_5_0;

use APP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class COA75_AddUserRoleEndEmail extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            [],
            'USER_ROLE_END',
            true,
        );
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
