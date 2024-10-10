<?php

/**
 * @file classes/migration/install/ReviewerSuggestionsMigration.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionsMigration
 *
 * @brief 
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReviewerSuggestionsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviewer_suggestions', function (Blueprint $table) {
            $table->comment('Author suggested reviewers at the submission time');
            $table->bigInteger('reviewer_suggestion_id')->autoIncrement();

            $table
                ->bigInteger('user_id')
                ->nullable()
                ->comment('The user/author who has made the suggestion');
            $table
                ->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
            $table->index(['user_id'], 'reviewer_suggestions_user_id');

            $table->bigInteger('submission_id')->comment('Submission at which the suggestion was made');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'reviewer_suggestions_submission_id');

            $table->string('email', 255)->comment('Suggested reviewer email address');
            $table->string('orcid_id', 255)->nullable()->comment('Suggested reviewer optional Orcid Id');

            $table
                ->timestamp('approved_at')
                ->nullable()
                ->comment('If and when the suggestion approved to add/invite suggested_reviewer');
            
            $table
                ->bigInteger('stage_id')
                ->nullable()
                ->comment('The stage at whihc suggestion approved');

            $table
                ->bigInteger('approver_id')
                ->nullable()
                ->comment('The user who has approved the suggestion');
            $table
                ->foreign('approver_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
            
            $table
                ->bigInteger('reviewer_id')
                ->nullable()
                ->comment('The reviewer who has been added/invited through this suggestion');
            $table
                ->foreign('reviewer_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

        });

        Schema::create('reviewer_suggestion_settings', function (Blueprint $table) {
            $table->comment('Reviewer suggestion settings table to contain multilingual or extra information');

            $table
                ->bigInteger('reviewer_suggestion_id')
                ->comment('The foreign key mapping of this setting to reviewer_suggestions table');
            
            $table
                ->foreign('reviewer_suggestion_id')
                ->references('reviewer_suggestion_id')
                ->on('reviewer_suggestions')
                ->onDelete('cascade');
            
            $table->index(['reviewer_suggestion_id'], 'reviewer_suggestion_settings_reviewer_suggestion_id');
            $table->string('locale', 28)->default('');

            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['reviewer_suggestion_id', 'locale', 'setting_name'], 'reviewer_suggestion_settings_unique');
            $table->index(['setting_name', 'locale'], 'reviewer_suggestion_settings_locale_setting_name_index');
        });
        
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('reviewer_suggestions');
        Schema::drop('reviewer_suggestion_settings');
        Schema::enableForeignKeyConstraints();
    }
}
