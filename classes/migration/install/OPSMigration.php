<?php

/**
 * @file classes/migration/install/OPSMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OPSMigration
 * @brief Describe database table structures.
 */

namespace APP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OPSMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Server sections.
        Schema::create('sections', function (Blueprint $table) {
            $table->bigInteger('section_id')->autoIncrement();

            $table->bigInteger('server_id');
            $table->foreign('server_id')->references('server_id')->on('servers')->onDelete('cascade');
            $table->index(['server_id'], 'sections_server_id');

            $table->bigInteger('review_form_id')->nullable();
            $table->foreign('review_form_id')->references('review_form_id')->on('review_forms')->onDelete('set null');
            $table->index(['review_form_id'], 'sections_review_form_id');

            $table->float('seq', 8, 2)->default(0);
            $table->tinyInteger('editor_restricted')->default(0);
            $table->tinyInteger('meta_indexed')->default(0);
            $table->tinyInteger('meta_reviewed')->default(1);
            $table->tinyInteger('abstracts_not_required')->default(0);
            $table->tinyInteger('hide_title')->default(0);
            $table->tinyInteger('hide_author')->default(0);
            $table->tinyInteger('is_inactive')->default(0);
            $table->bigInteger('abstract_word_count')->nullable();
        });

        // Section-specific settings
        Schema::create('section_settings', function (Blueprint $table) {
            $table->bigInteger('section_id');
            $table->foreign('section_id')->references('section_id')->on('sections')->onDelete('cascade');
            $table->index(['section_id'], 'section_settings_section_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();

            $table->unique(['section_id', 'locale', 'setting_name'], 'section_settings_pkey');
        });

        // Publications
        Schema::create('publications', function (Blueprint $table) {
            $table->comment('Each publication is one version of a submission.');
            $table->bigInteger('publication_id')->autoIncrement();
            $table->bigInteger('access_status')->default(0)->nullable();
            $table->date('date_published')->nullable();
            $table->datetime('last_modified')->nullable();

            $table->bigInteger('primary_contact_id')->nullable();
            $table->foreign('primary_contact_id', 'publications_author_id')->references('author_id')->on('authors')->onDelete('set null');
            $table->index(['primary_contact_id'], 'publications_author_id');

            $table->bigInteger('section_id')->nullable();
            $table->foreign('section_id')->references('section_id')->on('sections')->onDelete('set null');
            $table->index(['section_id'], 'publications_section_id');

            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'publications_submission_id');

            $table->tinyInteger('status')->default(1); // PKPSubmission::STATUS_QUEUED
            $table->string('url_path', 64)->nullable();
            $table->bigInteger('version')->nullable();
            $table->index(['url_path'], 'publications_url_path');

            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'publications_doi_id');
        });
        // The following foreign key relationships are for tables defined in SubmissionsMigration
        // but they depend on publications to exist so are created here.
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreign('current_publication_id', 'submissions_current_publication_id')->references('publication_id')->on('publications')->onDelete('set null');
        });
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->foreign('publication_id', 'publication_settings_publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
        });
        Schema::table('authors', function (Blueprint $table) {
            $table->foreign('publication_id', 'authors_publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
        });

        // Publication galleys
        Schema::create('publication_galleys', function (Blueprint $table) {
            $table->bigInteger('galley_id')->autoIncrement();
            $table->string('locale', 14)->nullable();

            $table->bigInteger('publication_id');
            $table->foreign('publication_id', 'publication_galleys_publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
            $table->index(['publication_id'], 'publication_galleys_publication_id');

            $table->string('label', 255)->nullable();

            $table->bigInteger('submission_file_id')->unsigned()->nullable();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('SET NULL');
            $table->index(['submission_file_id'], 'publication_galleys_submission_file_id');

            $table->float('seq', 8, 2)->default(0);
            $table->string('remote_url', 2047)->nullable();
            $table->tinyInteger('is_approved')->default(0);

            $table->string('url_path', 64)->nullable();
            $table->index(['url_path'], 'publication_galleys_url_path');

            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'publication_galleys_doi_id');
        });

        // Galley metadata.
        Schema::create('publication_galley_settings', function (Blueprint $table) {
            $table->bigInteger('galley_id');
            $table->foreign('galley_id')->references('galley_id')->on('publication_galleys');
            $table->index(['galley_id'], 'publication_galley_settings_galley_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();

            $table->unique(['galley_id', 'locale', 'setting_name'], 'publication_galley_settings_pkey');
        });


        // Add partial index (DBMS-specific)
        switch (DB::getDriverName()) {
            case 'mysql': DB::unprepared('CREATE INDEX publication_galley_settings_name_value ON publication_galley_settings (setting_name(50), setting_value(150))');
                break;
            case 'pgsql': DB::unprepared('CREATE INDEX publication_galley_settings_name_value ON publication_galley_settings (setting_name, setting_value)');
                break;
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('completed_payments');
        Schema::drop('sections');
        Schema::drop('section_settings');
        Schema::drop('publications');
        Schema::drop('publication_galleys');
        Schema::drop('publication_galley_settings');
    }
}
