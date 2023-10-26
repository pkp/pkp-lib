<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9462_UserUserGroups.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9462_UserUserGroups
 *
 * @brief Add start and end date to the user_user_groups table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9462_UserUserGroups extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_user_groups', function (Blueprint $table) {
            $table->datetime('date_start')->nullable();
            $table->datetime('date_end')->nullable();
            $table->dropUnique('user_user_groups_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('user_user_groups', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'date_start')) {
                $table->dropColumn('date_start');
            };
            if (Schema::hasColumn($table->getTable(), 'date_end')) {
                $table->dropColumn('date_end');
            };
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('user_user_groups');
            if (!array_key_exists('user_user_groups_unique', $indexesFound)) {
                $table->unique(['user_group_id', 'user_id'], 'user_user_groups_unique');
            }
        });
    }
}
