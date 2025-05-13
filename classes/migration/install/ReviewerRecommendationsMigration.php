<?php

/**
 * @file classes/migration/install/ReviewerRecommendationsMigration.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationsMigration
 *
 * @brief Describe database table structures .
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class ReviewerRecommendationsMigration extends \PKP\migration\Migration
{
    /**
     * The context table name
     */
    abstract public function contextTable(): string;

    /**
     * The context setting table name
     */
    abstract public function settingTable(): string;

    /**
     * The context's primary key
     */
    abstract public function contextPrimaryKey(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviewer_recommendations', function (Blueprint $table) {
            $table->comment('Review recommendation selected by reviewer at the completion of review assignment');
            $table->bigInteger('reviewer_recommendation_id')->autoIncrement();

            $table
                ->bigInteger('context_id')
                ->comment('Context for which the recommendation has been made');
            $table
                ->foreign('context_id')
                ->references($this->contextPrimaryKey())
                ->on($this->contextTable())
                ->onDelete('cascade');

            $table->index(['context_id'], 'reviewer_recommendations_context_id');

            $table
                ->boolean('status')
                ->default(true)
                ->comment('The status which determine if will be shown in recommendation list');

            $table->timestamps();

        });

        Schema::create('reviewer_recommendation_settings', function (Blueprint $table) {
            $table->comment('Reviewer recommendation settings table to contain multilingual or extra information');
            
            $table->bigIncrements('reviewer_recommendation_setting_id');
            
            $table
                ->bigInteger('reviewer_recommendation_id')
                ->comment('The foreign key mapping of this setting to reviewer_recommendation_id table');

            $table
                ->foreign(
                    'reviewer_recommendation_id',
                    'recommendation_settings_reviewer_recommendation_id_foreign'
                )
                ->references('reviewer_recommendation_id')
                ->on('reviewer_recommendations')
                ->onDelete('cascade');

            $table->index(['reviewer_recommendation_id'], 'reviewer_recommendation_settings_recommendation_id');
            $table->string('locale', 28)->default('');

            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['reviewer_recommendation_id', 'locale', 'setting_name'], 'reviewer_recommendation_settings_unique');
            $table->index(['setting_name', 'locale'], 'reviewer_recommendation_settings_locale_setting_name_index');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('reviewer_recommendation_settings');
        Schema::drop('reviewer_recommendations');
        Schema::enableForeignKeyConstraints();
    }
}
