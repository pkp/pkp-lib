<?php

/**
 * @file classes/migration/upgrade/3_4_0/I6807_SetLastModified.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6807_SetLastModified
 * @brief Update last modification dates where they are not yet set.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class I6807_SetLastModified extends \PKP\migration\Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // pkp/pkp-lib#6807 Make sure all issue last modification dates are set
        DB::statement('UPDATE submissions SET last_modified = NOW() WHERE last_modified IS NULL');
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to removed data');
    }
}
