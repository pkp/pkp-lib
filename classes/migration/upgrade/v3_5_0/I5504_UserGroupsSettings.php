<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I5504_UserGroupsSettings.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5504_UserGroupsSettings
 *
 * @brief Add permit_settings column to the user_groups table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I5504_UserGroupsSettings extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->smallInteger('permit_settings')->default(0);
        });
        DB::table('user_groups')->where('role_id', 1)->update(['permit_settings' => 1]); // role_id = 1 is ROLE_ID_SITE_ADMIN
        DB::table('user_groups')->where('role_id', 16)->update(['permit_settings' => 1]); // role_id = 16 is ROLE_ID_MANAGER
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'permit_settings')) {
                $table->dropColumn('permit_settings');
            };
        });
    }
}
