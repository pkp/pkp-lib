<?php

/**
 * @file classes/migration/LibraryFilesMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFilesMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class LibraryFilesMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Library files for a context
		Capsule::schema()->create('library_files', function (Blueprint $table) {
			$table->bigInteger('file_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->string('file_name', 255);
			$table->string('original_file_name', 255);
			$table->string('file_type', 255);
			$table->bigInteger('file_size');
			$table->smallInteger('type');
			$table->datetime('date_uploaded');
			$table->datetime('date_modified');
			$table->bigInteger('submission_id');
			$table->smallInteger('public_access')->default(0)->nullable();
			$table->index(['context_id'], 'library_files_context_id');
			$table->index(['submission_id'], 'library_files_submission_id');
		});

		// Library file metadata.
		Capsule::schema()->create('library_file_settings', function (Blueprint $table) {
			$table->bigInteger('file_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object|date)');
			$table->index(['file_id'], 'library_file_settings_id');
			$table->unique(['file_id', 'locale', 'setting_name'], 'library_file_settings_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('library_file_settings');
		Capsule::schema()->drop('library_files');
	}
}
