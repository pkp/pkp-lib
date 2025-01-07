<?php

/**
 * @file classes/migration/install/ReviewAssignmentSettings.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentSettings
 *
 * @brief Add review_assignment_settings table
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class ReviewAssignmentSettings extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_assignment_settings', function (Blueprint $table) {
            $table->bigIncrements('review_assignment_settings_id')->primary()->comment('Primary key.');
            $table->bigInteger('review_id')->comment('Foreign key referencing record in review_assignments table');
            $table->string('locale', 28)->nullable()->comment('Locale key.');
            $table->string('setting_name', 255)->comment('Name of settings record.');
            $table->mediumText('setting_value')->nullable()->comment('Settings value.');

            $table->unique(['review_id', 'locale', 'setting_name'], 'review_assignment_settings_unique');
            $table->foreign('review_id')->references('review_id')->on('review_assignments')->onDelete('cascade')->onUpdate('cascade');
            $table->index(['review_id'], 'review_assignment_settings_review_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('review_assignment_settings');
    }
}
