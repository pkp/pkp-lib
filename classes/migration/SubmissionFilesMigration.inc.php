<?php

/**
 * @file classes/migration/SubmissionFilesMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
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
			$table->bigInteger('file_id')->autoIncrement();
			$table->bigInteger('revision');
			$table->bigInteger('source_file_id')->nullable();
			$table->bigInteger('source_revision')->nullable();
			$table->bigInteger('submission_id');
			$table->string('file_type', 255);
			$table->bigInteger('genre_id')->nullable();
			$table->bigInteger('file_size');
			$table->string('original_file_name', 127)->nullable();
			$table->bigInteger('file_stage');
			$table->string('direct_sales_price', 255)->nullable();
			$table->string('sales_type', 255)->nullable();
			$table->tinyInteger('viewable')->nullable();
			$table->datetime('date_uploaded');
			$table->datetime('date_modified');
			$table->bigInteger('uploader_user_id')->nullable();
			$table->bigInteger('assoc_type')->nullable();
			$table->bigInteger('assoc_id')->nullable();
			$table->index(['submission_id'], 'submission_files_submission_id');
			//  pkp/pkp-lib#5804 
			$table->index(['file_stage', 'assoc_type', 'assoc_id'], 'submission_files_stage_assoc');
		});

		// Work-around for compound primary key
		switch (Capsule::connection()->getDriverName()) {
			case 'mysql': Capsule::connection()->unprepared("ALTER TABLE submission_files DROP PRIMARY KEY, ADD PRIMARY KEY (file_id, revision)"); break;
			case 'pgsql': Capsule::connection()->unprepared("ALTER TABLE submission_files DROP CONSTRAINT submission_files_pkey; ALTER TABLE submission_files ADD PRIMARY KEY (file_id, revision);"); break;
		}
		// Article supplementary file metadata.
		Capsule::schema()->create('submission_file_settings', function (Blueprint $table) {
			$table->bigInteger('file_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object|date)');
			$table->index(['file_id'], 'submission_file_settings_id');
			$table->unique(['file_id', 'locale', 'setting_name'], 'submission_file_settings_pkey');
		});

		// Submission visuals.
		Capsule::schema()->create('submission_artwork_files', function (Blueprint $table) {
			$table->bigInteger('file_id');
			$table->bigInteger('revision');
			$table->text('caption')->nullable();
			$table->string('credit', 255)->nullable();
			$table->string('copyright_owner', 255)->nullable();
			$table->text('copyright_owner_contact')->nullable();
			$table->text('permission_terms')->nullable();
			$table->bigInteger('permission_file_id')->nullable();
			$table->bigInteger('chapter_id')->nullable();
			$table->bigInteger('contact_author')->nullable();
		});

		// Submission supplementary content.
		Capsule::schema()->create('submission_supplementary_files', function (Blueprint $table) {
			$table->bigInteger('file_id');
			$table->bigInteger('revision');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('submission_supplementary_files');
		Capsule::schema()->drop('submission_artwork_files');
		Capsule::schema()->drop('submission_file_settings');
		Capsule::schema()->drop('submission_files');
	}
}
