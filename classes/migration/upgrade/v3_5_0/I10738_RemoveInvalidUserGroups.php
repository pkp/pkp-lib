<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10738_RemoveInvalidUserGroups.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10738_RemoveInvalidUserGroups
 *
 * @brief Remove invalid Site Admin groups with a context association.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I10738_RemoveInvalidUserGroups extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_groups')
            ->where('role_id', 1) // Role::ROLE_ID_SITE_ADMIN
            ->whereNotNull('context_id')
            ->delete();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // noop
    }
}
