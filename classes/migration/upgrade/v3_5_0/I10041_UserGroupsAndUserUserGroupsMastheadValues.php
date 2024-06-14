<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10041_UserGroupsAndUserUserGroupsMastheadValues.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10041_UserGroupsAndUserUserGroupsMastheadValues
 *
 * @brief Consider existing default masthead roles and all user-role assignments to be dispalyed on masthead.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I10041_UserGroupsAndUserUserGroupsMastheadValues extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userGroupIds = DB::table('user_group_settings')
            ->where('setting_name', 'nameLocaleKey')
            ->where(
                fn (Builder $q) =>
                    $q->where('setting_value', 'default.groups.name.editor')
                        ->orWhere('setting_value', 'default.groups.name.sectionEditor')
                        ->orWhere('setting_value', 'default.groups.name.externalReviewer')
            )
            ->pluck('user_group_id');
        DB::table('user_groups')
            ->whereIn('user_group_id', $userGroupIds)
            ->update(['masthead' => 1]);
        DB::table('user_user_groups')
            ->update(['masthead' => 1]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $userGroupIds = DB::table('user_group_settings')
            ->where('setting_name', 'nameLocaleKey')
            ->where(
                fn (Builder $q) =>
                    $q->where('setting_value', 'default.groups.name.editor')
                        ->orWhere('setting_value', 'default.groups.name.sectionEditor')
                        ->orWhere('setting_value', 'default.groups.name.externalReviewer')
            )
            ->pluck('user_group_id');
        DB::table('user_groups')
            ->whereIn('user_group_id', $userGroupIds)
            ->update(['masthead' => 0]);
        DB::table('user_user_groups')
            ->update(['masthead' => null]);
    }
}
