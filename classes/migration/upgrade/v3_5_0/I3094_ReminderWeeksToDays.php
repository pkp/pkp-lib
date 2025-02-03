<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I3094_ReminderWeeksToDays.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I3094_ReminderWeeksToDays
 *
 * @brief Migrate numWeeksPerResponse and numWeeksPerReview from weeks to days
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I3094_ReminderWeeksToDays extends Migration
{

    private const CONTEXT_SETTING_TABLE_NAMES = [
        'ojs2' => 'journal_settings',
        'omp' => 'press_settings',
        'ops' => 'server_settings',
    ];

    private string $settingsTableName;

    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $applicationName = Application::get()->getName();
        $this->settingsTableName = self::CONTEXT_SETTING_TABLE_NAMES[$applicationName];

        DB::table($this->$settingsTableName)->where('setting_name', 'numWeeksPerResponse')->update(['setting_name' => 'numDaysPerResponse']);
        DB::table($this->$settingsTableName)->where('setting_name', 'numWeeksPerReview')->update(['setting_name' => 'numDaysPerReview']);
        DB::statement("UPDATE " . $this->$settingsTableName . " SET setting_value = setting_value * 7 WHERE setting_name = 'numDaysPerResponse'");
        DB::statement("UPDATE " . $this->$settingsTableName . " SET setting_value = setting_value * 7 WHERE setting_name = 'numDaysPerReview'");

    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
