<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7366_UpdateUserAPIKeySettings.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7366_UpdateUserAPIKeySettings
 *
 * @brief Describe upgrade/downgrade for updating user API related settings
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I7366_UpdateUserAPIKeySettings extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::select(
            DB::raw(
                "SELECT u.user_id FROM users u 
                JOIN user_settings enabled_setting ON (enabled_setting.user_id = u.user_id AND enabled_setting.setting_name = 'apiKeyEnabled')
                LEFT JOIN user_settings key_setting ON (key_setting.user_id = u.user_id AND key_setting.setting_name = 'apiKey') 
                WHERE key_setting.user_id IS NULL"
            )
        );

        collect($users)
            ->pluck('user_id')
            ->chunk(1000)
            ->each(
                fn ($ids) => DB::table('user_settings')
                    ->where('setting_name', 'apiKeyEnabled')
                    ->whereIn('user_id', $ids->toArray())
                    ->delete()
            );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
