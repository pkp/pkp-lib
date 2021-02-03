<?php

/**
 * @file classes/migration/ReviewsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ReviewsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Reviewing assignments.
		Capsule::schema()->create('review_assignments', function (Blueprint $table) {
			$table->bigInteger('review_id')->autoIncrement();
			$table->bigInteger('submission_id');
			$table->bigInteger('reviewer_id');
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
			$table->smallInteger('stage_id');

			import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment'); // for constant
			$table->smallInteger('review_method')->default(SUBMISSION_REVIEW_METHOD_ANONYMOUS);

			$table->smallInteger('round')->default(1);
			$table->smallInteger('step')->default(1);
			$table->bigInteger('review_form_id')->nullable();
			$table->smallInteger('unconsidered')->nullable();
			$table->index(['submission_id'], 'review_assignments_submission_id');
			$table->index(['reviewer_id'], 'review_assignments_reviewer_id');
			$table->index(['review_form_id'], 'review_assignments_form_id');
			$table->index(['reviewer_id', 'review_id'], 'review_assignments_reviewer_review');
		});

		// Review rounds.
		Capsule::schema()->create('review_rounds', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->autoIncrement();
			$table->bigInteger('submission_id');
			$table->bigInteger('stage_id')->nullable();
			$table->smallInteger('round');
			$table->bigInteger('review_revision')->nullable();
			$table->bigInteger('status')->nullable();
			$table->index(['submission_id'], 'review_rounds_submission_id');
			$table->unique(['submission_id', 'stage_id', 'round'], 'review_rounds_submission_id_stage_id_round_pkey');
		});

		// Submission Files for each review round
		Capsule::schema()->create('review_round_files', function (Blueprint $table) {
			$table->bigInteger('submission_id');
			$table->bigInteger('review_round_id');
			$table->smallInteger('stage_id');
			$table->bigInteger('submission_file_id')->nullable(false)->unsigned();
			$table->index(['submission_id'], 'review_round_files_submission_id');
			$table->unique(['submission_id', 'review_round_id', 'submission_file_id'], 'review_round_files_pkey');
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
		});

		// Associates reviewable submission files with reviews
		Capsule::schema()->create('review_files', function (Blueprint $table) {
			$table->bigInteger('review_id');
			$table->bigInteger('submission_file_id')->nullable(false)->unsigned();
			$table->index(['review_id'], 'review_files_review_id');
			$table->unique(['review_id', 'submission_file_id'], 'review_files_pkey');
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('review_files');
		Capsule::schema()->drop('review_round_files');
		Capsule::schema()->drop('review_rounds');
		Capsule::schema()->drop('review_assignments');
	}
}
