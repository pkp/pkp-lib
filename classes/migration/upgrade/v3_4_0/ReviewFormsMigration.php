<?php

/**
 * @file classes/migration/install/ReviewFormsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormsMigration
 * @brief Describe database table structures. This is a copy of the installation ReviewFormsMigration with the foreign keys removed, which
 *        roughly corresponds to the version of the migration that shipped with 3.3.0. This is because foreign keys are added later in the
 *        I6093 migration and adding them here (via the installation migration) would attempt to add them twice.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReviewFormsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Review forms.
        if (!Schema::hasTable('review_forms')) {
            Schema::create('review_forms', function (Blueprint $table) {
                $table->bigInteger('review_form_id')->autoIncrement();
                $table->bigInteger('assoc_type');
                $table->bigInteger('assoc_id');
                $table->float('seq', 8, 2)->nullable();
                $table->smallInteger('is_active')->nullable();
            });
        }

        // Review form settings
        if (!Schema::hasTable('review_form_settings')) {
            Schema::create('review_form_settings', function (Blueprint $table) {
                $table->bigInteger('review_form_id');
                $table->string('locale', 14)->default('');
                $table->string('setting_name', 255);
                $table->mediumText('setting_value')->nullable();
                $table->string('setting_type', 6);
                $table->unique(['review_form_id', 'locale', 'setting_name'], 'review_form_settings_pkey');
            });
        }

        // Review form elements.
        if (!Schema::hasTable('review_form_elements')) {
            Schema::create('review_form_elements', function (Blueprint $table) {
                $table->bigInteger('review_form_element_id')->autoIncrement();
                $table->bigInteger('review_form_id');
                $table->float('seq', 8, 2)->nullable();
                $table->bigInteger('element_type')->nullable();
                $table->smallInteger('required')->nullable();
                $table->smallInteger('included')->nullable();
            });
        }

        // Review form element settings
        if (!Schema::hasTable('review_form_element_settings')) {
            Schema::create('review_form_element_settings', function (Blueprint $table) {
                $table->bigInteger('review_form_element_id');
                $table->string('locale', 14)->default('');
                $table->string('setting_name', 255);
                $table->mediumText('setting_value')->nullable();
                $table->string('setting_type', 6);
                $table->unique(['review_form_element_id', 'locale', 'setting_name'], 'review_form_element_settings_pkey');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('review_form_element_settings');
        Schema::drop('review_form_elements');
        Schema::drop('review_form_settings');
        Schema::drop('review_forms');
    }
}
