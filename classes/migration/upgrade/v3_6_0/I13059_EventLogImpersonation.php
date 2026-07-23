<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13059_EventLogImpersonation.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13059_EventLogImpersonation
 *
 * @brief Add an `impersonated_as_user_id` column to the `event_log` table so that
 *   actions performed while impersonating another user (the "Login as" mechanism)
 *   record both the real actor (`user_id`) and the impersonated, acted-as account
 *   (`impersonated_as_user_id`).
 *
 * @see https://github.com/pkp/pkp-lib/issues/13059
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13059_EventLogImpersonation extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('event_log', function (Blueprint $table) {
            $table->bigInteger('impersonated_as_user_id')->nullable()->after('user_id')
                ->comment('The user that was impersonated via the "Login as" mechanism when the event was performed');
            $table->foreign('impersonated_as_user_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['impersonated_as_user_id'], 'event_log_impersonated_as_user_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('event_log', function (Blueprint $table) {
            $table->dropForeign(['impersonated_as_user_id']);
            $table->dropIndex('event_log_impersonated_as_user_id');
            $table->dropColumn('impersonated_as_user_id');
        });
    }
}
