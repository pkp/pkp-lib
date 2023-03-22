<?php

/**
 * @file classes/migration/install/DoiMigration.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class DoiMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // NB: A foreign key constraint is placed on context_id, but this is application dependent and
        // needs to be added once the context has been created.
        // It will reference the app specific column (e.g. journal_id, press_id, etc.).
        Schema::create('dois', function (Blueprint $table) {
            $table->comment('Stores all DOIs used in the system.');
            $table->bigInteger('doi_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'dois_context_id');

            $table->string('doi');
            $table->smallInteger('status')->default(1);
        });

        // Settings
        Schema::create('doi_settings', function (Blueprint $table) {
            $table->comment('More data about DOIs, including the registration agency.');
            $table->bigIncrements('doi_setting_id');
            $table->bigInteger('doi_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['doi_id', 'locale', 'setting_name'], 'doi_settings_unique');
            $table->index(['doi_id'], 'doi_settings_doi_id');
            $table->foreign('doi_id')->references('doi_id')->on('dois')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('dois');
        Schema::drop('doi_settings');
    }
}
