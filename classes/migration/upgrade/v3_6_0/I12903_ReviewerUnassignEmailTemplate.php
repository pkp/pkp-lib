<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12903_ReviewerUnassignEmailTemplate.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12903_ReviewerUnassignEmailTemplate
 *
 * @brief Update email template for reviewer unassign
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12903_ReviewerUnassignEmailTemplate extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            templatesFile:Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            emailKey: 'REVIEWER_UNASSIGN',
        );

        Repo::emailTemplate()->dao->installEmailTemplates(
            templatesFile:Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            emailKey: 'REVIEW_CANCEL',
        );
    }

    /**
     * @inheritDoc
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
