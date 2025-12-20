<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10819_OrcidOauthScopeMail.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10819_OrcidOauthScopeMail
 *
 * @brief Add new email template for updating users' OAuth scope
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10819_OrcidOauthScopeMail extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            [],
            'ORCID_REQUEST_UPDATE_SCOPE',
            true,
        );
    }

    /**
     * @inheritDoc
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
