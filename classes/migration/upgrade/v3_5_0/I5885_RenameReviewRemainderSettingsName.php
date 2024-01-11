<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I5885_RenameReviewRemainderSettingsName.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5885_RenameReviewRemainderColumns
 *
 * @brief Rename the review remainder settings name
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

abstract class I5885_RenameReviewRemainderSettingsName extends Migration
{
    abstract protected function getContextSettingsTable(): string;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        DB::table($this->getContextSettingsTable())
            ->select(['setting_name'])
            ->where('setting_name', 'numDaysBeforeInviteReminder')
            ->update([
                'setting_name' => 'numDaysAfterReviewResponseReminderDue'
            ]);
        
        DB::table($this->getContextSettingsTable())
            ->select(['setting_name'])
            ->where('setting_name', 'numDaysBeforeSubmitReminder')
            ->update([
                'setting_name' => 'numDaysAfterReviewSubmitReminderDue'
            ]);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        DB::table($this->getContextSettingsTable())
            ->select(['setting_name'])
            ->where('setting_name', 'numDaysAfterReviewResponseReminderDue')
            ->update([
                'setting_name' => 'numDaysBeforeInviteReminder'
            ]);
        
        DB::table($this->getContextSettingsTable())
            ->select(['setting_name'])
            ->where('setting_name', 'numDaysAfterReviewSubmitReminderDue')
            ->update([
                'setting_name' => 'numDaysBeforeSubmitReminder'
            ]);
    }
}
