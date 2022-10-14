<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6093_AddForeignKeys.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Describe upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I6093_AddForeignKeys extends \PKP\migration\upgrade\v3_4_0\I6093_AddForeignKeys
{
    protected function getContextTable(): string
    {
        return 'journals';
    }

    protected function getContextSettingsTable(): string
    {
        return 'journal_settings';
    }

    protected function getContextKeyField(): string
    {
        return 'journal_id';
    }

    public function up(): void
    {
        parent::up();

        Schema::table('sections', function (Blueprint $table) {
            $table->foreign('review_form_id')->references('review_form_id')->on('review_forms')->onDelete('set null');
            $table->index(['review_form_id'], 'sections_review_form_id');
        });

        Schema::table('section_settings', function (Blueprint $table) {
            $table->foreign('section_id')->references('section_id')->on('sections')->onDelete('cascade');
        });

        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->foreign('publication_id', 'publication_galleys_publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
            $table->index(['submission_file_id'], 'publication_galleys_submission_file_id');
        });

        Schema::table('publication_galley_settings', function (Blueprint $table) {
            $table->foreign('galley_id')->references('galley_id')->on('publication_galleys');
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->foreign('primary_contact_id', 'publications_author_id')->references('author_id')->on('authors')->onDelete('set null');
            $table->index(['primary_contact_id'], 'publications_primary_contact_id');

            $table->foreign('section_id', 'publications_section_id')->references('section_id')->on('sections')->onDelete('cascade');
            $table->foreign('submission_id', 'publications_submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
        });
    }
}
