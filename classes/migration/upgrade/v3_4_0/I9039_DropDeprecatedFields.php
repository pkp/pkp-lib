<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9039_DropDeprecatedFields.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9039_DropDeprecatedFields
 *
 * @brief Drop deprecated fields
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I9039_DropDeprecatedFields extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Drop the deprecated fields from user_settings
        foreach (['assoc_id', 'assoc_type'] as $column) {
            if (Schema::hasColumn('user_settings', $column)) {
                Schema::dropColumns('user_settings', $column);
            }
        }
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
