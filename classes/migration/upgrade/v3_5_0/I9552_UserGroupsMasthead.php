<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9552_UserGroupsMasthead.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9552_UserGroupsMasthead
 *
 * @brief Add masthead column to the user_groups table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9552_UserGroupsMasthead extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->smallInteger('masthead')->default(0);
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'masthead')) {
                $table->dropColumn('masthead');
            };
        });
    }
}
