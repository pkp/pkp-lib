<?php

/**
 * @file classes/migration/install/ReviewFormsMigration.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundAuthorResponse
 *
 * @brief Add database tables related to author review responses.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReviewRoundAuthorResponse extends \PKP\migration\Migration
{
    public function up(): void
    {
        // create review_round_settings table
        Schema::create('review_round_settings', function (Blueprint $table) {
            $table->bigInteger('review_round_setting_id')->autoIncrement();
            $table->bigInteger('review_round_id');
            $table->string('locale')->nullable();
            $table->string('setting_name');
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)'); // ReviewRoundDAO extends \PKP\db\DAO which requires setting type

            $table->foreign('review_round_id')
                ->references('review_round_id')
                ->on('review_rounds')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['review_round_id'], 'review_round_settings_review_round_id');
        });

        Schema::create('review_round_author_responses', function (Blueprint $table) {
            $table->bigInteger('response_id')->autoIncrement()->comment('Primary key.');
            $table->bigInteger('review_round_id')->comment('ID of the review round the response belongs to.');
            $table->bigInteger('user_id')->comment('User ID of the assigned author participant that is submitting the response.');
            $table->bigInteger('doi_id')->nullable()->comment('DOI ID for the DOI assigned to the author response');
            $table->timestamps();

            $table->foreign('review_round_id')
                ->references('review_round_id')
                ->on('review_rounds')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('doi_id')
                ->references('doi_id')
                ->on('dois')
                ->nullOnDelete();

            $table->index(['review_round_id'], 'review_round_author_responses_review_round_id');
            $table->index(['user_id'], 'review_round_author_responses_user_id');
            $table->index(['review_round_id', 'user_id'], 'review_round_author_responses_review_round_id_user_id');
            $table->index(['doi_id'], 'review_round_author_responses_doi_id');
        });

        Schema::create('review_round_author_response_settings', function (Blueprint $table) {
            $table->bigInteger('response_setting_id')->autoIncrement();
            $table->bigInteger('response_id')->comment('ID of the review round response this setting entry belongs to.');
            $table->string('locale', 28)->nullable();
            $table->string('setting_name');
            $table->text('setting_value')->nullable();

            $table->foreign('response_id')
                ->references('response_id')
                ->on('review_round_author_responses')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['response_id'], 'review_round_author_response_settings_response_id');
            $table->index(['response_setting_id'], 'review_round_author_response_settings_response_setting_id');
        });

        Schema::create('review_round_author_response_authors', function (Blueprint $table) {
            $table->comment('Associates authors with responses submitted for review rounds(which are stored in `review_round_author_responses`).');

            $table->bigInteger('review_round_author_response_author_id')->autoIncrement();
            $table->bigInteger('response_id')->comment('ID of the review round author response.');
            $table->bigInteger('author_id')->comment('ID of the author to associate with the response.');

            $table->foreign('response_id')
                ->references('response_id')
                ->on('review_round_author_responses')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('author_id')
                ->references('author_id')
                ->on('authors')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['response_id'], 'review_round_author_response_authors_response_id');
            $table->index(['author_id'], 'review_round_author_response_authors_author_id');
        });
    }

    public function down(): void
    {
        Schema::drop('review_round_settings');
        Schema::drop('review_round_author_response_settings');
        Schema::drop('review_round_author_response_authors');
        Schema::drop('review_round_author_responses');
    }
}
