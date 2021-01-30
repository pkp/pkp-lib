<?php

/**
 * @file classes/migration/TombstoneMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TombstoneMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class TombstoneMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Unnavailable data object tombstones.
		Capsule::schema()->create('data_object_tombstones', function (Blueprint $table) {
			$table->bigInteger('tombstone_id')->autoIncrement();
			$table->bigInteger('data_object_id');
			$table->datetime('date_deleted');
			$table->string('set_spec', 255);
			$table->string('set_name', 255);
			$table->string('oai_identifier', 255);
			$table->index(['data_object_id'], 'data_object_tombstones_data_object_id');
		});

		// Data object tombstone settings.
		Capsule::schema()->create('data_object_tombstone_settings', function (Blueprint $table) {
			$table->bigInteger('tombstone_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
			$table->index(['tombstone_id'], 'data_object_tombstone_settings_tombstone_id');
			$table->unique(['tombstone_id', 'locale', 'setting_name'], 'data_object_tombstone_settings_pkey');
		});

		// Objects that are part of a data object tombstone OAI set.
		Capsule::schema()->create('data_object_tombstone_oai_set_objects', function (Blueprint $table) {
			$table->bigInteger('object_id')->autoIncrement();
			$table->bigInteger('tombstone_id');
			$table->bigInteger('assoc_type');
			$table->bigInteger('assoc_id');
			$table->index(['tombstone_id'], 'data_object_tombstone_oai_set_objects_tombstone_id');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('data_object_tombstone_oai_set_objects');
		Capsule::schema()->drop('data_object_tombstone_settings');
		Capsule::schema()->drop('data_object_tombstones');
	}
}
