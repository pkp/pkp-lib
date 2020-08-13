<?php

/**
 * @file classes/migration/ReviewFormsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ReviewFormsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Review forms.
		Capsule::schema()->create('review_forms', function (Blueprint $table) {
			$table->bigInteger('review_form_id')->autoIncrement();

			// pkp/pkp-lib#6093 FIXME: Can't declare relationship constraints on assoc_type/assoc_id pairs
			$table->bigInteger('assoc_type');
			$table->bigInteger('assoc_id');

			$table->float('seq', 8, 2)->nullable();
			$table->tinyInteger('is_active')->nullable();
		});

		// Review form settings
		Capsule::schema()->create('review_form_settings', function (Blueprint $table) {
			$table->bigInteger('review_form_id');
			$table->foreign('review_form_id')->references('review_form_id')->on('review_forms');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);

			$table->index(['review_form_id'], 'review_form_settings_review_form_id');
			$table->unique(['review_form_id', 'locale', 'setting_name'], 'review_form_settings_pkey');
		});

		// Review form elements.
		Capsule::schema()->create('review_form_elements', function (Blueprint $table) {
			$table->bigInteger('review_form_element_id')->autoIncrement();

			$table->bigInteger('review_form_id');
			$table->foreign('review_form_id')->references('review_form_id')->on('review_forms');

			$table->float('seq', 8, 2)->nullable();
			$table->bigInteger('element_type')->nullable();
			$table->tinyInteger('required')->nullable();
			$table->tinyInteger('included')->nullable();

			$table->index(['review_form_id'], 'review_form_elements_review_form_id');
		});

		// Review form element settings
		Capsule::schema()->create('review_form_element_settings', function (Blueprint $table) {
			$table->bigInteger('review_form_element_id');
			$table->foreign('review_form_element_id')->references('review_form_element_id')->on('review_form_elements');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);

			$table->index(['review_form_element_id'], 'review_form_element_settings_review_form_element_id');
			$table->unique(['review_form_element_id', 'locale', 'setting_name'], 'review_form_element_settings_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('review_form_element_settings');
		Capsule::schema()->drop('review_form_elements');
		Capsule::schema()->drop('review_form_settings');
		Capsule::schema()->drop('review_forms');
	}
}
