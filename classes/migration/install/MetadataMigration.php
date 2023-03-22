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
            $table->comment('A citation made by an associated publication.');
            $table->bigInteger('citation_id')->autoIncrement();

            $table->bigInteger('publication_id');
            $table->foreign('publication_id', 'citations_publication')->references('publication_id')->on('publications')->onDelete('cascade');
            $table->index(['publication_id'], 'citations_publication');

            $table->text('raw_citation');
            $table->bigInteger('seq')->default(0);

            $table->unique(['publication_id', 'seq'], 'citations_publication_seq');
        });

        // Citation settings
        Schema::create('citation_settings', function (Blueprint $table) {
            $table->comment('Additional data about citations, including localized content.');
            $table->bigIncrements('citation_setting_id');
            $table->bigInteger('citation_id');
            $table->foreign('citation_id', 'citation_settings_citation_id')->references('citation_id')->on('citations')->onDelete('cascade');
            $table->index(['citation_id'], 'citation_settings_citation_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->unique(['citation_id', 'locale', 'setting_name'], 'citation_settings_unique');
        });

        // Filter groups
        Schema::create('filter_groups', function (Blueprint $table) {
            $table->comment('Filter groups are used to organized filters into named sets, which can be retrieved by the application for invocation.');
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
            $table->comment('Filters represent a transformation of a supported piece of data from one form to another, such as a PHP object into an XML document.');
            $table->bigInteger('filter_id')->autoIncrement();

            $table->bigInteger('filter_group_id')->default(0);
            $table->foreign('filter_group_id')->references('filter_group_id')->on('filter_groups')->onDelete('cascade');
            $table->index(['filter_group_id'], 'filters_filter_group_id');

            $table->bigInteger('context_id')->default(0);
            $table->string('display_name', 255)->nullable();
            $table->string('class_name', 255)->nullable();
            $table->smallInteger('is_template')->default(0);
            $table->bigInteger('parent_filter_id')->default(0);
            $table->bigInteger('seq')->default(0);
        });

        // Filter Settings
        Schema::create('filter_settings', function (Blueprint $table) {
            $table->comment('More data about filters, including localized content.');
            $table->bigIncrements('filter_setting_id');
            $table->bigInteger('filter_id');
            $table->foreign('filter_id')->references('filter_id')->on('filters')->onDelete('cascade');
            $table->index(['filter_id'], 'filter_settings_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->unique(['filter_id', 'locale', 'setting_name'], 'filter_settings_unique');
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
        Schema::drop('citation_settings');
        Schema::drop('citations');
    }
}
