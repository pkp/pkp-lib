<?php

/**
 * @file classes/migration/install/SubmissionSearchMigration.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearchMigration
 *
 * @brief Describe database table for DB-backed submission search index.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SubmissionSearchMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('submissions_fulltext', function (Blueprint $table) {
            $table->comment('Fulltext search index for submission content');

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
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('submissions_fulltext');
    }
}
