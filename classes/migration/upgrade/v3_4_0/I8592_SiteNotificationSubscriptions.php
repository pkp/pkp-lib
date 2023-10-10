<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8592_SiteNotificationSubscriptions.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8592_SiteNotificationSubscriptions
 *
 * @brief Allow notification subscriptions to have no context for site-wide subscriptions
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I8592_SiteNotificationSubscriptions extends \PKP\migration\Migration
{
    public function up(): void
    {
        Schema::table('notification_subscription_settings', function (Blueprint $table) {
            $table->bigInteger('context')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_subscription_settings', function (Blueprint $table) {
            $table->bigInteger('context')->nullable(false)->change();
        });
    }
}
