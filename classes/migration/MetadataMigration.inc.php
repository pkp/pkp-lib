<?php

/**
 * @file classes/migration/MetadataMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class MetadataMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Citations
		Capsule::schema()->create('citations', function (Blueprint $table) {
			$table->bigInteger('citation_id')->autoIncrement();

			$table->bigInteger('publication_id')->default(0);
			$table->foreign('publication_id')->references('publication_id')->on('publications');

			$table->text('raw_citation');
			$table->bigInteger('seq')->default(0);

			$table->index(['publication_id'], 'citations_publication');
			$table->unique(['publication_id', 'seq'], 'citations_publication_seq');
		});

		// Citation settings
		Capsule::schema()->create('citation_settings', function (Blueprint $table) {
			$table->bigInteger('citation_id');
			$table->foreign('citation_id')->references('citation_id')->on('citations');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);

			$table->index(['citation_id'], 'citation_settings_citation_id');
			$table->unique(['citation_id', 'locale', 'setting_name'], 'citation_settings_pkey');
		});

		// Metadata Descriptions
		Capsule::schema()->create('metadata_descriptions', function (Blueprint $table) {
			$table->bigInteger('metadata_description_id')->autoIncrement();

			// pkp/pkp-lib#6093 Can't declare relationship constraints on assoc_type/assoc_id pairs
			$table->bigInteger('assoc_type')->default(0);
			$table->bigInteger('assoc_id')->default(0);

			$table->string('schema_namespace', 255);
			$table->string('schema_name', 255);
			$table->string('display_name', 255)->nullable();
			$table->bigInteger('seq')->default(0);
			$table->index(['assoc_type', 'assoc_id'], 'metadata_descriptions_assoc');
		});

		// Metadata Description Settings
		Capsule::schema()->create('metadata_description_settings', function (Blueprint $table) {
			$table->bigInteger('metadata_description_id');
			$table->foreign('metadata_description_id')->references('metadata_description_id')->on('metadata_descriptions');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);

			$table->index(['metadata_description_id'], 'metadata_description_settings_id');
			$table->unique(['metadata_description_id', 'locale', 'setting_name'], 'metadata_descripton_settings_pkey');
		});

		// Filter groups
		Capsule::schema()->create('filter_groups', function (Blueprint $table) {
			$table->bigInteger('filter_group_id')->autoIncrement();
			$table->string('symbolic', 255)->nullable();
			$table->string('display_name', 255)->nullable();
			$table->string('description', 255)->nullable();
			$table->string('input_type', 255)->nullable();
			$table->string('output_type', 255)->nullable();

			$table->unique(['symbolic'], 'filter_groups_symbolic');
		});

		// Configured filter instances (transformations)
		Capsule::schema()->create('filters', function (Blueprint $table) {
			$table->bigInteger('filter_id')->autoIncrement();

			$table->bigInteger('filter_group_id');
			$table->foreign('filter_group_id')->references('filter_group_id')->on('filter_groups');

			// pkp/pkp-lib#6093 FIXME: Can't set constraint with default 0 (CONTEXT_SITE)
			$table->bigInteger('context_id')->default(0);

			$table->string('display_name', 255)->nullable();
			$table->string('class_name', 255)->nullable();
			$table->tinyInteger('is_template')->default(0);
			$table->bigInteger('parent_filter_id')->default(0);
			$table->bigInteger('seq')->default(0);
		});

		// Filter Settings
		Capsule::schema()->create('filter_settings', function (Blueprint $table) {
			$table->bigInteger('filter_id');
			$table->foreign('filter_id')->references('filter_id')->on('filters');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);

			$table->index(['filter_id'], 'filter_settings_id');
			$table->unique(['filter_id', 'locale', 'setting_name'], 'filter_settings_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('filter_settings');
		Capsule::schema()->drop('filters');
		Capsule::schema()->drop('filter_groups');
		Capsule::schema()->drop('metadata_description_settings');
		Capsule::schema()->drop('metadata_descriptions');
		Capsule::schema()->drop('citation_settings');
		Capsule::schema()->drop('citations');
	}
}
