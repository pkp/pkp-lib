<?php

/**
 * @file classes/migration/install/TombstoneMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TombstoneMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TombstoneMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Unnavailable data object tombstones.
        Schema::create('data_object_tombstones', function (Blueprint $table) {
            $table->bigInteger('tombstone_id')->autoIncrement();
            $table->bigInteger('data_object_id');
            $table->datetime('date_deleted');
            $table->string('set_spec', 255);
            $table->string('set_name', 255);
            $table->string('oai_identifier', 255);
            $table->index(['data_object_id'], 'data_object_tombstones_data_object_id');
        });

        // Data object tombstone settings.
        Schema::create('data_object_tombstone_settings', function (Blueprint $table) {
            $table->bigInteger('tombstone_id');
            $table->foreign('tombstone_id', 'data_object_tombstone_settings_tombstone_id')->references('tombstone_id')->on('data_object_tombstones')->onDelete('cascade');
            $table->index(['tombstone_id'], 'data_object_tombstone_settings_tombstone_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');

            $table->unique(['tombstone_id', 'locale', 'setting_name'], 'data_object_tombstone_settings_pkey');
        });

        // Objects that are part of a data object tombstone OAI set.
        Schema::create('data_object_tombstone_oai_set_objects', function (Blueprint $table) {
            $table->bigInteger('object_id')->autoIncrement();

            $table->bigInteger('tombstone_id');
            $table->foreign('tombstone_id', 'data_object_tombstone_oai_set_objects_tombstone_id')->references('tombstone_id')->on('data_object_tombstones')->onDelete('cascade');
            $table->index(['tombstone_id'], 'data_object_tombstone_oai_set_objects_tombstone_id');

            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('data_object_tombstone_oai_set_objects');
        Schema::drop('data_object_tombstone_settings');
        Schema::drop('data_object_tombstones');
    }
}
