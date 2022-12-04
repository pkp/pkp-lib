<?php

/**
 * @file classes/migration/install/ReviewsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReviewsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Review rounds.
        Schema::create('review_rounds', function (Blueprint $table) {
            $table->bigInteger('review_round_id')->autoIncrement();
            $table->bigInteger('submission_id');
            $table->bigInteger('stage_id')->nullable();
            $table->smallInteger('round');
            $table->bigInteger('review_revision')->nullable();
            $table->bigInteger('status')->nullable();
            $table->index(['submission_id'], 'review_rounds_submission_id');
            $table->unique(['submission_id', 'stage_id', 'round'], 'review_rounds_submission_id_stage_id_round_pkey');
        });
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->foreign('review_round_id')->references('review_round_id')->on('review_rounds')->onDelete('cascade');
            $table->index(['review_round_id'], 'edit_decisions_review_round_id');
        });

        // Reviewing assignments.
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->bigInteger('review_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions');
            $table->index(['submission_id'], 'review_assignments_submission_id');

            $table->bigInteger('reviewer_id');
            $table->foreign('reviewer_id')->references('user_id')->on('users');
            $table->index(['reviewer_id'], 'review_assignments_reviewer_id');

            $table->text('competing_interests')->nullable();
            $table->smallInteger('recommendation')->nullable();
            $table->datetime('date_assigned')->nullable();
            $table->datetime('date_notified')->nullable();
            $table->datetime('date_confirmed')->nullable();
            $table->datetime('date_completed')->nullable();
            $table->datetime('date_acknowledged')->nullable();
            $table->datetime('date_due')->nullable();
            $table->datetime('date_response_due')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->smallInteger('reminder_was_automatic')->default(0);
            $table->smallInteger('declined')->default(0);
            $table->smallInteger('cancelled')->default(0);
            $table->bigInteger('reviewer_file_id')->nullable();
            $table->datetime('date_rated')->nullable();
            $table->datetime('date_reminded')->nullable();
            $table->smallInteger('quality')->nullable();

            $table->bigInteger('review_round_id');
            $table->foreign('review_round_id')->references('review_round_id')->on('review_rounds');
            $table->index(['review_round_id', 'reviewer_id'], 'review_assignment_reviewer_round');

            $table->smallInteger('stage_id');

            $table->smallInteger('review_method')->default(\PKP\submission\reviewAssignment\ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS);

            $table->smallInteger('round')->default(1);
            $table->smallInteger('step')->default(1);

            $table->bigInteger('review_form_id')->nullable();
            $table->foreign('review_form_id')->references('review_form_id')->on('review_forms');
            $table->index(['review_form_id'], 'review_assignments_form_id');

            $table->smallInteger('unconsidered')->nullable();
            $table->smallInteger('request_resent')->default(0);

            $table->dateTime('review_confirmed_at')->nullable();

            $table->bigInteger('review_confirming_user_id')->nullable();
            $table->foreign('review_confirming_user_id')->references('user_id')->on('users')->onDelete('set null');

            // Normally reviewer can't be assigned twice on the same review round.
            // HOWEVER, if two reviewer user accounts are subsequently merged, both will keep
            // separate review assignments but the reviewer_id will become the same!
            // (https://github.com/pkp/pkp-lib/issues/7678)
            $table->index(['reviewer_id', 'review_id'], 'review_assignments_reviewer_review');
        });

        // Review form responses.
        if (!Schema::hasTable('review_form_responses')) {
            Schema::create('review_form_responses', function (Blueprint $table) {
                $table->bigInteger('review_form_element_id');
                $table->foreign('review_form_element_id')->references('review_form_element_id')->on('review_form_elements')->onDelete('cascade');
                $table->index(['review_form_element_id'], 'review_form_responses_review_form_element_id');

                $table->bigInteger('review_id');
                $table->foreign('review_id')->references('review_id')->on('review_assignments')->onDelete('cascade');
                $table->index(['review_id'], 'review_form_responses_review_id');

                $table->string('response_type', 6)->nullable();
                $table->text('response_value')->nullable();

                $table->index(['review_form_element_id', 'review_id'], 'review_form_responses_pkey');
            });
        }

        // Submission Files for each review round
        Schema::create('review_round_files', function (Blueprint $table) {
            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'review_round_files_submission_id');

            $table->bigInteger('review_round_id');
            $table->smallInteger('stage_id');

            $table->bigInteger('submission_file_id')->nullable(false)->unsigned();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['submission_file_id'], 'review_round_files_submission_file_id');

            $table->unique(['submission_id', 'review_round_id', 'submission_file_id'], 'review_round_files_pkey');
        });

        // Associates reviewable submission files with reviews
        Schema::create('review_files', function (Blueprint $table) {
            $table->bigInteger('review_id');
            $table->foreign('review_id')->references('review_id')->on('review_assignments')->onDelete('cascade');
            $table->index(['review_id'], 'review_files_review_id');

            $table->bigInteger('submission_file_id')->nullable(false)->unsigned();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['submission_file_id'], 'review_files_submission_file_id');

            $table->unique(['review_id', 'submission_file_id'], 'review_files_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('review_form_responses');
        Schema::drop('review_assignments');
        Schema::drop('review_files');
        Schema::drop('review_round_files');
        Schema::drop('review_rounds');
    }
}
