<?php

/**
 * @file classes/migration/CategoriesMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoriesMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CategoriesMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Permits the organization of content into categories.
		Capsule::schema()->create('categories', function (Blueprint $table) {
			$table->bigInteger('category_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->bigInteger('parent_id');
			$table->bigInteger('seq')->nullable();
			$table->string('path', 255);
			$table->text('image')->nullable();
			$table->index(['context_id', 'parent_id'], 'category_context_id');
			$table->unique(['context_id', 'path'], 'category_path');
		});

		// Category-specific settings
		Capsule::schema()->create('category_settings', function (Blueprint $table) {
			$table->bigInteger('category_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
			$table->unique(['category_id', 'locale', 'setting_name'], 'category_settings_pkey');
		});

		// Associations for categories and publications.
		Capsule::schema()->create('publication_categories', function (Blueprint $table) {
			$table->bigInteger('publication_id');
			$table->bigInteger('category_id');
			$table->unique(['publication_id', 'category_id'], 'publication_categories_id');
		});

	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('categories');
		Capsule::schema()->drop('category_settings');
		Capsule::schema()->drop('publication_categories');
	}
}
