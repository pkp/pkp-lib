<?php

/**
 * @file classes/migration/SubmissionFilesMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class SubmissionFilesMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Files associated with submission. Includes submission files, etc.
		Capsule::schema()->create('submission_files', function (Blueprint $table) {
			$table->bigIncrements('submission_file_id');
			$table->bigInteger('submission_id');
			$table->bigInteger('file_id')->nullable(false)->unsigned();
			$table->bigInteger('source_submission_file_id')->nullable();
			$table->bigInteger('genre_id')->nullable();
			$table->bigInteger('file_stage');
			$table->string('direct_sales_price', 255)->nullable();
			$table->string('sales_type', 255)->nullable();
			$table->smallInteger('viewable')->nullable();
			$table->datetime('created_at');
			$table->datetime('updated_at');
			$table->bigInteger('uploader_user_id')->nullable();
			$table->bigInteger('assoc_type')->nullable();
			$table->bigInteger('assoc_id')->nullable();
			$table->index(['submission_id'], 'submission_files_submission_id');
			//  pkp/pkp-lib#5804
			$table->index(['file_stage', 'assoc_type', 'assoc_id'], 'submission_files_stage_assoc');
			$table->foreign('file_id')->references('file_id')->on('files');
		});

		// Article supplementary file metadata.
		Capsule::schema()->create('submission_file_settings', function (Blueprint $table) {
			$table->bigInteger('submission_file_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->default('string')->comment('(bool|int|float|string|object|date)');
			$table->index(['submission_file_id'], 'submission_file_settings_id');
			$table->unique(['submission_file_id', 'locale', 'setting_name'], 'submission_file_settings_pkey');
		});

		// Submission file revisions
		Capsule::schema()->create('submission_file_revisions', function (Blueprint $table) {
			$table->bigIncrements('revision_id');
			$table->bigInteger('submission_file_id')->unsigned();
			$table->bigInteger('file_id')->unsigned();
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
			$table->foreign('file_id')->references('file_id')->on('files');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('submission_file_revisions');
		Capsule::schema()->drop('submission_file_settings');
		Capsule::schema()->drop('submission_files');
	}
}
