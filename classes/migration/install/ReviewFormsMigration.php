<?php

/**
 * @file classes/migration/install/ReviewFormsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormsMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

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
                $table->comment('Review forms provide custom templates for peer reviews with several types of questions.');
                $table->bigInteger('review_form_id')->autoIncrement();
                $table->bigInteger('assoc_type');
                $table->bigInteger('assoc_id');
                $table->float('seq')->nullable();
                $table->smallInteger('is_active')->nullable();
            });
        }

        // Review form settings
        if (!Schema::hasTable('review_form_settings')) {
            Schema::create('review_form_settings', function (Blueprint $table) {
                $table->comment('More data about review forms, including localized content such as names.');
                $table->bigIncrements('review_form_setting_id');
                $table->bigInteger('review_form_id');
                $table->foreign('review_form_id', 'review_form_settings_review_form_id')->references('review_form_id')->on('review_forms')->onDelete('cascade');
                $table->index(['review_form_id'], 'review_form_settings_review_form_id');

                $table->string('locale', 28)->default('');
                $table->string('setting_name', 255);
                $table->mediumText('setting_value')->nullable();
                $table->string('setting_type', 6);

                $table->unique(['review_form_id', 'locale', 'setting_name'], 'review_form_settings_unique');
            });
        }

        // Review form elements.
        if (!Schema::hasTable('review_form_elements')) {
            Schema::create('review_form_elements', function (Blueprint $table) {
                $table->comment('Each review form element represents a single question on a review form.');
                $table->bigInteger('review_form_element_id')->autoIncrement();

                $table->bigInteger('review_form_id');
                $table->foreign('review_form_id', 'review_form_elements_review_form_id')->references('review_form_id')->on('review_forms')->onDelete('cascade');
                $table->index(['review_form_id'], 'review_form_elements_review_form_id');

                $table->float('seq')->nullable();
                $table->bigInteger('element_type')->nullable();
                $table->smallInteger('required')->nullable();
                $table->smallInteger('included')->nullable();
            });
        }

        // Review form element settings
        if (!Schema::hasTable('review_form_element_settings')) {
            Schema::create('review_form_element_settings', function (Blueprint $table) {
                $table->comment('More data about review form elements, including localized content such as question text.');
                $table->bigIncrements('review_form_element_setting_id');
                $table->bigInteger('review_form_element_id');
                $table->foreign('review_form_element_id', 'review_form_element_settings_review_form_element_id')->references('review_form_element_id')->on('review_form_elements')->onDelete('cascade');
                $table->index(['review_form_element_id'], 'review_form_element_settings_review_form_element_id');

                $table->string('locale', 28)->default('');
                $table->string('setting_name', 255);
                $table->mediumText('setting_value')->nullable();
                $table->string('setting_type', 6);

                $table->unique(['review_form_element_id', 'locale', 'setting_name'], 'review_form_element_settings_unique');
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
