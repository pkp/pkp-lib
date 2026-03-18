<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11800_AddUserRoleMastheadUpdateEmail.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11800_AddUserRoleMastheadUpdateEmail
 *
 * @brief Adds user role masthead update email template
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I11800_AddUserRoleMastheadUpdateEmail extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            [],
            'USER_ROLE_MASTHEAD_UPDATE',
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
