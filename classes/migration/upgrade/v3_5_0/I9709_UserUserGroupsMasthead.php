<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9709_UserUserGroupsMasthead.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9709_UserUserGroupsMasthead
 *
 * @brief Add masthead column to the user_user_groups table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9709_UserUserGroupsMasthead extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_user_groups', function (Blueprint $table) {
            $table->smallInteger('masthead')->nullable();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('user_user_groups', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'masthead')) {
                $table->dropColumn('masthead');
            };
        });
    }
}
