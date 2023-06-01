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
        $fieldMap = [
            'user_settings' => ['assoc_id', 'assoc_type'],
            'review_assignments' => ['reviewer_file_id']
        ];
        foreach ($fieldMap as $entity => $columns) {
            if (Schema::hasColumns($entity, $columns)) {
                Schema::dropColumns($entity, $columns);
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
