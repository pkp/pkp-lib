<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I6278_DataCitations.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6278_DataCitations.php
 *
 * @brief Adds migration for Data Citations
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I6278_DataCitations extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Data Citations
        Schema::create('data_citations', function (Blueprint $table) {
            $table->comment('A data citation pointing to a related data set.');
            $table->bigInteger('data_citation_id')->autoIncrement();

            $table->bigInteger('publication_id');
            $table->foreign('publication_id', 'data_citations_publication')->references('publication_id')->on('publications')->onDelete('cascade');
            $table->index(['publication_id'], 'data_citations_publication');

            $table->bigInteger('seq')->default(0);

        });

        // Data Citation settings
        Schema::create('data_citation_settings', function (Blueprint $table) {
            $table->comment('Additional data about data citations, including localized content.');
            $table->bigIncrements('data_citation_setting_id');
            $table->bigInteger('data_citation_id');
            $table->foreign('data_citation_id', 'data_citation_settings_data_citation_id')->references('data_citation_id')->on('data_citations')->onDelete('cascade');
            $table->index(['data_citation_id'], 'data_citation_settings_data_citation_id');

            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['data_citation_id', 'locale', 'setting_name'], 'data_citation_settings_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
