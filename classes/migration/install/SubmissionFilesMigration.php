<?php

/**
 * @file classes/migration/install/SubmissionFilesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SubmissionFilesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Files associated with submission. Includes submission files, etc.
        Schema::create('submission_files', function (Blueprint $table) {
            $table->bigIncrements('submission_file_id');

            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'submission_files_submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'submission_files_submission_id');

            $table->bigInteger('file_id')->nullable(false)->unsigned();
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
            $table->index(['file_id'], 'submission_files_file_id');

            // FK declared below table (circular reference)
            $table->bigInteger('source_submission_file_id')->unsigned()->nullable();

            $table->bigInteger('genre_id')->nullable();
            $table->foreign('genre_id')->references('genre_id')->on('genres')->onDelete('set null');
            $table->index(['genre_id'], 'submission_files_genre_id');

            $table->bigInteger('file_stage');
            $table->string('direct_sales_price', 255)->nullable();
            $table->string('sales_type', 255)->nullable();
            $table->smallInteger('viewable')->nullable();
            $table->datetime('created_at');
            $table->datetime('updated_at');

            $table->bigInteger('uploader_user_id')->nullable();
            $table->foreign('uploader_user_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['uploader_user_id'], 'submission_files_uploader_user_id');

            $table->bigInteger('assoc_type')->nullable();
            $table->bigInteger('assoc_id')->nullable();

            //  pkp/pkp-lib#5804
            $table->index(['file_stage', 'assoc_type', 'assoc_id'], 'submission_files_stage_assoc');
        });
        Schema::table('submission_files', function (Blueprint $table) {
            $table->foreign('source_submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['source_submission_file_id'], 'submission_files_source_submission_file_id');
        });

        // Article supplementary file metadata.
        Schema::create('submission_file_settings', function (Blueprint $table) {
            $table->foreignId('submission_file_id');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['submission_file_id'], 'submission_file_settings_submission_file_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->default('string')->comment('(bool|int|float|string|object|date)');

            $table->unique(['submission_file_id', 'locale', 'setting_name'], 'submission_file_settings_pkey');
        });

        // Submission file revisions
        Schema::create('submission_file_revisions', function (Blueprint $table) {
            $table->bigIncrements('revision_id');

            $table->bigInteger('submission_file_id')->unsigned();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['submission_file_id'], 'submission_file_revisions_submission_file_id');

            $table->bigInteger('file_id')->unsigned();
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
            $table->index(['file_id'], 'submission_file_revisions_file_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('submission_file_revisions');
        Schema::drop('submission_file_settings');
        Schema::drop('submission_files');
    }
}
