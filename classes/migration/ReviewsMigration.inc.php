<?php

/**
 * @file classes/migration/ReviewsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
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
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->bigInteger('reviewer_id');
			$table->foreign('reviewer_id')->references('user_id')->on('users');

			$table->text('competing_interests')->nullable();
			$table->tinyInteger('recommendation')->nullable();
			$table->datetime('date_assigned')->nullable();
			$table->datetime('date_notified')->nullable();
			$table->datetime('date_confirmed')->nullable();
			$table->datetime('date_completed')->nullable();
			$table->datetime('date_acknowledged')->nullable();
			$table->datetime('date_due')->nullable();
			$table->datetime('date_response_due')->nullable();
			$table->datetime('last_modified')->nullable();
			$table->tinyInteger('reminder_was_automatic')->default(0);
			$table->tinyInteger('declined')->default(0);
			$table->tinyInteger('cancelled')->default(0);
			$table->bigInteger('reviewer_file_id')->nullable();
			$table->datetime('date_rated')->nullable();
			$table->datetime('date_reminded')->nullable();
			$table->tinyInteger('quality')->nullable();
			$table->bigInteger('review_round_id');
			$table->tinyInteger('stage_id')->default(1); // WORKFLOW_STAGE_ID_SUBMISSION
			$table->tinyInteger('review_method')->default(1);
			$table->tinyInteger('round')->default(1);
			$table->tinyInteger('step')->default(1);

			$table->bigInteger('review_form_id')->nullable();
			$table->foreign('review_form_id')->references('review_form_id')->on('review_forms');

			$table->tinyInteger('unconsidered')->nullable();

			$table->index(['submission_id'], 'review_assignments_submission_id');
			$table->index(['reviewer_id'], 'review_assignments_reviewer_id');
			$table->index(['review_form_id'], 'review_assignments_form_id');
			$table->index(['reviewer_id', 'review_id'], 'review_assignments_reviewer_review');
		});

		// Review rounds.
		Capsule::schema()->create('review_rounds', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->autoIncrement();

			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->bigInteger('stage_id')->nullable();
			$table->tinyInteger('round');
			$table->bigInteger('review_revision')->nullable();
			$table->bigInteger('status')->nullable();

			$table->index(['submission_id'], 'review_rounds_submission_id');
			$table->unique(['submission_id', 'stage_id', 'round'], 'review_rounds_submission_id_stage_id_round_pkey');
		});

		// Submission Files for each review round
		Capsule::schema()->create('review_round_files', function (Blueprint $table) {
			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->bigInteger('review_round_id');
			$table->foreign('review_round_id')->references('review_round_id')->on('review_rounds');

			$table->tinyInteger('stage_id');

			$table->bigInteger('file_id');
			// pkp/pkp-lib#6093 FIXME: Compound foreign key
			// $table->foreign('file_id')->references('file_id')->on('submission_files');

			$table->bigInteger('revision')->default(1);

			$table->index(['submission_id'], 'review_round_files_submission_id');
			$table->unique(['submission_id', 'review_round_id', 'file_id', 'revision'], 'review_round_files_pkey');
		});

		// Associates reviewable submission files with reviews
		Capsule::schema()->create('review_files', function (Blueprint $table) {
			$table->bigInteger('review_id');
			$table->foreign('review_id')->references('review_id')->on('review_assignments');

			$table->bigInteger('file_id');
			// pkp/pkp-lib#6093 FIXME: Compound foreign key
			// $table->foreign('file_id')->references('file_id')->on('submission_files');

			$table->index(['review_id'], 'review_files_review_id');
			$table->unique(['review_id', 'file_id'], 'review_files_pkey');
		});

		// Review form responses.
		Capsule::schema()->create('review_form_responses', function (Blueprint $table) {
			$table->bigInteger('review_form_element_id');
			$table->foreign('review_form_element_id')->references('review_form_element_id')->on('review_form_elements');

			$table->bigInteger('review_id');
			$table->foreign('review_id')->references('review_id')->on('review_assignments');

			$table->string('response_type', 6)->nullable();
			$table->text('response_value')->nullable();

			$table->index(['review_form_element_id', 'review_id'], 'review_form_responses_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('review_form_responses');
		Capsule::schema()->drop('review_files');
		Capsule::schema()->drop('review_round_files');
		Capsule::schema()->drop('review_rounds');
		Capsule::schema()->drop('review_assignments');
	}
}
