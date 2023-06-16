<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7874_NotificationMetadataModifiedRemove.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7874_NotificationMetadataModifiedRemove
 *
 * @brief Removes deprecated PKPNotification::NOTIFICATION_TYPE_METADATA_MODIFIED setting
 *
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7874_NotificationMetadataModifiedRemove extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notification_subscription_settings')
            ->where('setting_value', '=', 0x1000002) // PKP\notification\PKPNotification::NOTIFICATION_TYPE_METADATA_MODIFIED
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
