<?php

/**
 * @file classes/migration/upgrade/v3_4_0/UpgradeMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpgradeMigration
 *
 * @brief Describe upgrade/downgrade operations from 3.3.x to 3.4.0.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpgradeMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pkp/pkp-lib#6093: Set up foreign key constraints
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->foreign('submission_id')->references('submission_id')->on('submissions');
            $table->foreign('reviewer_id')->references('user_id')->on('users');
            $table->foreign('review_round_id')->references('review_round_id')->on('review_rounds');
            $table->foreign('review_form_id')->references('review_form_id')->on('review_forms');

            // Normally reviewer can't be assigned twice on the same review round.
            // HOWEVER, if two reviewer user accounts are subsequently merged, both will keep
            // separate review assignments but the reviewer_id will become the same!
            // (https://github.com/pkp/pkp-lib/issues/7678)
            $table->index(['review_round_id', 'reviewer_id'], 'review_assignment_reviewer_round');
        });

        // pkp/pkp-lib#6685: Drop old tombstones table in OJS and OPS
        Schema::dropIfExists('submission_tombstones');

        // pkp/pkp-lib#7246: Allow default null values for the last login date
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('date_last_login')->nullable()->change();
        });

        // pkp/pkp-lib#7246: Remove setting_type in user_settings
        if (Schema::hasColumn('user_settings', 'setting_type')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->dropColumn('setting_type');
            });
        }

        // pkp/pkp-lib#8093: Remove setting_type in user_group_settings
        if (Schema::hasColumn('user_group_settings', 'setting_type')) {
            Schema::table('user_group_settings', function (Blueprint $table) {
                $table->dropColumn('setting_type');
            });
        }
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['submission_id']);
            $table->dropForeign(['review_round_id']);
            $table->dropForeign(['review_form_id']);
        });
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropIndex('review_assignment_reviewer_round');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('date_last_login')->nullable(false)->default(null)->change();
        });
    }
}
