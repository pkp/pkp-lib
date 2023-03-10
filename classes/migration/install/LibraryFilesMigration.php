<?php

/**
 * @file classes/migration/install/LibraryFilesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFilesMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LibraryFilesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Library files for a context
        Schema::create('library_files', function (Blueprint $table) {
            $table->comment('Library files can be associated with the context (press/server/journal) or with individual submissions, and are typically forms, agreements, and other administrative documents that are not part of the scholarly content.');
            $table->bigInteger('file_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id', 'library_files_context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'library_files_context_id');

            $table->string('file_name', 255);
            $table->string('original_file_name', 255);
            $table->string('file_type', 255);
            $table->bigInteger('file_size');
            $table->smallInteger('type');
            $table->datetime('date_uploaded');
            $table->datetime('date_modified');

            $table->bigInteger('submission_id')->nullable();
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'library_files_submission_id');

            $table->smallInteger('public_access')->default(0)->nullable();
        });

        // Library file metadata.
        Schema::create('library_file_settings', function (Blueprint $table) {
            $table->comment('More data about library files, including localized content such as names.');
            $table->bigInteger('file_id');
            $table->foreign('file_id')->references('file_id')->on('library_files')->onDelete('cascade');
            $table->index(['file_id'], 'library_file_settings_file_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object|date)');

            $table->unique(['file_id', 'locale', 'setting_name'], 'library_file_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('library_file_settings');
        Schema::drop('library_files');
    }
}
