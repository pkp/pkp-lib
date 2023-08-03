<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9197_MigrateAccessKeys.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9197_MigrateAccessKeys
 *
 * @brief Convert access keys to invitations.
 */

namespace PKP\migration\upgrade\v3_5_0;

use PKP\install\DowngradeNotSupportedException;

class I9197_MigrateAccessKeys extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // FIXME: Needs migration added
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
