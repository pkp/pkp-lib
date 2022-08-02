<?php

/**
 * @file classes/migration/install/MetadataMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MetadataMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Citations
        Schema::create('citations', function (Blueprint $table) {
            $table->bigInteger('citation_id')->autoIncrement();
            $table->bigInteger('publication_id')->default(0);
            $table->text('raw_citation');
            $table->bigInteger('seq')->default(0);
            $table->index(['publication_id'], 'citations_publication');
            $table->unique(['publication_id', 'seq'], 'citations_publication_seq');
        });

        // Citation settings
        Schema::create('citation_settings', function (Blueprint $table) {
            $table->bigInteger('citation_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->index(['citation_id'], 'citation_settings_citation_id');
            $table->unique(['citation_id', 'locale', 'setting_name'], 'citation_settings_pkey');
        });

        // Metadata Descriptions
        Schema::create('metadata_descriptions', function (Blueprint $table) {
            $table->bigInteger('metadata_description_id')->autoIncrement();
            $table->bigInteger('assoc_type')->default(0);
            $table->bigInteger('assoc_id')->default(0);
            $table->string('schema_namespace', 255);
            $table->string('schema_name', 255);
            $table->string('display_name', 255)->nullable();
            $table->bigInteger('seq')->default(0);
            $table->index(['assoc_type', 'assoc_id'], 'metadata_descriptions_assoc');
        });

        // Metadata Description Settings
        Schema::create('metadata_description_settings', function (Blueprint $table) {
            $table->bigInteger('metadata_description_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->index(['metadata_description_id'], 'metadata_description_settings_id');
            $table->unique(['metadata_description_id', 'locale', 'setting_name'], 'metadata_descripton_settings_pkey');
        });

        // Filter groups
        Schema::create('filter_groups', function (Blueprint $table) {
            $table->bigInteger('filter_group_id')->autoIncrement();
            $table->string('symbolic', 255)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('input_type', 255)->nullable();
            $table->string('output_type', 255)->nullable();
            $table->unique(['symbolic'], 'filter_groups_symbolic');
        });

        // Configured filter instances (transformations)
        Schema::create('filters', function (Blueprint $table) {
            $table->bigInteger('filter_id')->autoIncrement();
            $table->bigInteger('filter_group_id')->default(0);
            $table->bigInteger('context_id')->default(0);
            $table->string('display_name', 255)->nullable();
            $table->string('class_name', 255)->nullable();
            $table->smallInteger('is_template')->default(0);
            $table->bigInteger('parent_filter_id')->default(0);
            $table->bigInteger('seq')->default(0);
        });

        // Filter Settings
        Schema::create('filter_settings', function (Blueprint $table) {
            $table->bigInteger('filter_id');
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
     */
    public function down(): void
    {
        Schema::drop('filter_settings');
        Schema::drop('filters');
        Schema::drop('filter_groups');
        Schema::drop('metadata_description_settings');
        Schema::drop('metadata_descriptions');
        Schema::drop('citation_settings');
        Schema::drop('citations');
    }
}
