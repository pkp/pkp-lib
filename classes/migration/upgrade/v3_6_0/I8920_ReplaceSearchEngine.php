<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I8920_ReplaceSearchEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8920_ReplaceSearchEngine
 *
 * @brief Replace the search engine implementation with database-backed fulltext.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I8920_ReplaceSearchEngine extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('submission_search_object_keywords');
        Schema::drop('submission_search_objects');
        Schema::drop('submission_search_keyword_list');

        Schema::create('submissions_fulltext', function (Blueprint $table) {
            $table->bigInteger('submissions_fulltext_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');

            $table->bigInteger('publication_id');
            $table->foreign('publication_id')->references('publication_id')->on('publications')->onDelete('cascade');

            $table->string('locale', 28);

            $table->text('title');
            $table->text('abstract');
            $table->text('body');
            $table->text('authors');

            $table->fulltext(['title', 'abstract', 'body', 'authors']);
            $table->unique(['submission_id', 'publication_id', 'locale']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \Exception('Downgrade not supported.');
    }
}
