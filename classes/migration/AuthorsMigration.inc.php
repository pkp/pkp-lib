<?php

/**
 * @file classes/migration/AuthorsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AuthorsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Authors for submissions.
		Capsule::schema()->create('authors', function (Blueprint $table) {
			$table->bigInteger('author_id')->autoIncrement();
			$table->string('email', 90);
			$table->tinyInteger('include_in_browse')->default(1);

			$table->bigInteger('publication_id');
			$table->foreign('publication_id')->references('publication_id')->on('publications');

			$table->float('seq', 8, 2)->default(0);
			$table->bigInteger('user_group_id')->nullable();

			$table->index(['publication_id'], 'authors_publication_id');
		});

		// Language dependent author metadata.
		Capsule::schema()->create('author_settings', function (Blueprint $table) {
			$table->bigInteger('author_id');
			$table->foreign('author_id')->references('author_id')->on('authors');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();

			$table->index(['author_id'], 'author_settings_author_id');
			$table->unique(['author_id', 'locale', 'setting_name'], 'author_settings_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('author_settings');
		Capsule::schema()->drop('authors');
	}
}
