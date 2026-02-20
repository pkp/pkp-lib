<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12307_ReviewRoundRequestAuthorResponseMail.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12307_ReviewRoundRequestAuthorResponseMail
 *
 * @brief Add new email template for request author's response to reviewers' comments.
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12307_ReviewRoundRequestAuthorResponseMail extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            templatesFile:Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            emailKey: 'REQUEST_REVIEW_ROUND_AUTHOR_RESPONSE',
            skipExisting: true,
            recordTemplateGroupAccess: true,
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
