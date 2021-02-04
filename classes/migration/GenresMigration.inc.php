<?php

/**
 * @file classes/migration/GenresMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenresMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class GenresMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// A context's submission file genres.
		Capsule::schema()->create('genres', function (Blueprint $table) {
			$table->bigInteger('genre_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->bigInteger('seq');
			$table->smallInteger('enabled')->default(1);

			import('lib.pkp.classes.submission.Genre'); // for constant
			$table->bigInteger('category')->default(GENRE_CATEGORY_DOCUMENT);

			$table->smallInteger('dependent')->default(0);
			$table->smallInteger('supplementary')->default(0);
			$table->string('entry_key', 30)->nullable();
		});

		// Genre settings
		Capsule::schema()->create('genre_settings', function (Blueprint $table) {
			$table->bigInteger('genre_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
			$table->index(['genre_id'], 'genre_settings_genre_id');
			$table->unique(['genre_id', 'locale', 'setting_name'], 'genre_settings_pkey');
		});

	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('genre_settings');
		Capsule::schema()->drop('genres');
	}
}
