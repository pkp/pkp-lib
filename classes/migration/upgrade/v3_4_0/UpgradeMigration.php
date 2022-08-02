<?php

/**
 * @file classes/migration/upgrade/v3_4_0/UpgradeMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPv3_4_0UpgradeMigration
 * @brief Describe upgrade/downgrade operations from 3.3.x to 3.4.0.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpgradeMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pkp/pkp-lib#6093: Delete review_assignment entries that correspond to nonexistent submissions.
        $orphanedIds = DB::table('review_assignments AS ra')->leftJoin('submissions AS s', 'ra.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->pluck('ra.submission_id', 'ra.review_id');
        foreach ($orphanedIds as $reviewId => $submissionId) {
            error_log("Removing orphaned review_assignments entry ID ${reviewId} with submission_id ${submissionId}");
            DB::table('review_assignments')->where('review_id', '=', $reviewId)->delete();
        }

        // pkp/pkp-lib#6093: Delete review_assignment entries that correspond to nonexistent reviewers.
        $orphanedIds = DB::table('review_assignments AS ra')->leftJoin('users AS u', 'ra.reviewer_id', '=', 'u.user_id')->whereNull('u.user_id')->pluck('ra.reviewer_id', 'ra.review_id');
        foreach ($orphanedIds as $reviewId => $userId) {
            error_log("Removing orphaned review_assignments entry ID ${reviewId} with reviewer_id ${userId}");
            DB::table('review_assignments')->where('review_id', '=', $reviewId)->delete();
        }

        // pkp/pkp-lib#6093: Delete review_assignment entries that correspond to nonexistent review rounds.
        $orphanedIds = DB::table('review_assignments AS ra')->leftJoin('review_rounds AS rr', 'ra.review_round_id', '=', 'rr.review_round_id')->whereNull('rr.review_round_id')->pluck('ra.review_round_id', 'ra.review_id');
        foreach ($orphanedIds as $reviewId => $reviewRoundId) {
            error_log("Removing orphaned review_assignments entry ID ${reviewId} with review_round_id ${reviewRoundId}");
            DB::table('review_assignments')->where('review_id', '=', $reviewId)->delete();
        }

        // pkp/pkp-lib#6093: Delete review_assignment entries that correspond to nonexistent review forms.
        $orphanedIds = DB::table('review_assignments AS ra')->leftJoin('review_forms AS rf', 'ra.review_form_id', '=', 'rf.review_form_id')->whereNull('rf.review_form_id')->whereNotNull('ra.review_form_id')->pluck('ra.review_form_id', 'ra.review_id');
        foreach ($orphanedIds as $reviewId => $reviewFormId) {
            error_log("Using default review form for review with ID ${reviewId} which refers to nonexistent review_form_id ${reviewFormId}");
            DB::table('review_assignments')->where('review_id', '=', $reviewId)->update(['review_form_id' => null]);
        }

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
