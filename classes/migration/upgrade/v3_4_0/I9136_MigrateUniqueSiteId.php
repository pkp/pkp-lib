<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9136_MigrateUniqueSiteId.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9136_MigrateUniqueSiteId
 *
 * @brief Migrate UsageEvent plugin setting 'uniqueSiteId' to the site_settings table.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\core\PKPString;
use PKP\install\DowngradeNotSupportedException;

class I9136_MigrateUniqueSiteId extends \PKP\migration\Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        $newUniqueSiteId = DB::table('site_settings')
            ->where('setting_name', '=', 'uniqueSiteId')
            ->value('setting_value');
        if (is_null($newUniqueSiteId)) {
            $oldUniqueSiteId = DB::table('plugin_settings')
                ->where('plugin_name', '=', 'usageeventplugin')
                ->where('setting_name', '=', 'uniqueSiteId')
                ->value('setting_value');
            $uniqueSiteId = is_null($oldUniqueSiteId) || strlen($oldUniqueSiteId) == 0 ? PKPString::generateUUID() : $oldUniqueSiteId;
            DB::table('site_settings')->insert([
                'setting_name' => 'uniqueSiteId',
                'setting_value' => $uniqueSiteId
            ]);
            DB::table('plugin_settings')
                ->where('plugin_name', '=', 'usageeventplugin')
                ->where('setting_name', '=', 'uniqueSiteId')
                ->delete();
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
