<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11971_RemoveShowTitleInUserGroups.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11971_RemoveShowTitleInUserGroups
 *
 * @brief Remove column show_title in the DB table user_groups
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I11971_RemoveShowTitleInUserGroups extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (Schema::hasColumn('user_groups', 'show_title')) {
            Schema::table('user_groups', function (Blueprint $table) {
                $table->dropColumn('show_title');
            });
        }
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
