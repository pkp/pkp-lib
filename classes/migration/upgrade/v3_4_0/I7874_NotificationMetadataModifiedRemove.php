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
 * @brief Removes deprecated Notification::NOTIFICATION_TYPE_METADATA_MODIFIED setting
 *
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;
use stdClass;

class I7874_NotificationMetadataModifiedRemove extends Migration
{
    protected Collection $subscribedToMetadataChangedNotification;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->subscribedToMetadataChangedNotification = DB::table('notification_subscription_settings')
            ->where('setting_value', '=', 0x1000002) // PKP\notification\Notification::NOTIFICATION_TYPE_METADATA_MODIFIED
            ->get();

        $this->subscribedToMetadataChangedNotification->each(function (stdClass $row) {
            DB::table('notification_subscription_settings')
                ->where('setting_id', '=', $row->{'setting_id'})
                ->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->subscribedToMetadataChangedNotification->each(function (stdClass $row) {
            $values = (array) $row;
            unset($values['setting_id']);
            DB::table('notification_subscription_settings')->insert([$values]);
        });
    }
}
