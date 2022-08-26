<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8093_UpdateUserGroupRelationTablesFK.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8093_UpdateUserGroupRelationTablesFK
 * @brief Update the foreign keys for UserGroup relation tables
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use Illuminate\Database\Schema\Blueprint;

class I8093_UpdateUserGroupRelationTablesFK extends Migration
{
    public function up(): void
    {
        Schema::table('user_group_settings', function (Blueprint $table) {
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
        });

        Schema::table('user_user_groups', function (Blueprint $table) {
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
        });

        Schema::table('user_group_stage', function (Blueprint $table) {
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_group_settings', function (Blueprint $table) {
            $table->dropForeign('user_group_settings_user_group_id_foreign');
        });

        Schema::table('user_user_groups', function (Blueprint $table) {
            $table->dropForeign('user_user_groups_user_group_id_foreign');
        });

        Schema::table('user_group_stage', function (Blueprint $table) {
            $table->dropForeign('user_group_stage_user_group_id_foreign');
        });
    }
}
