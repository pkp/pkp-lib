<?php

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12048_ReviewRoundAuthorResponse extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        // create review_round_settings table
        Schema::create('review_round_settings', function (Blueprint $table) {
            $table->bigInteger('review_round_setting_id')->autoIncrement();
            $table->bigInteger('review_round_id');
            $table->string('locale')->nullable();
            $table->string('setting_name');
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');

            $table->foreign('review_round_id')
                ->references('review_round_id')
                ->on('review_rounds')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['review_round_id'], 'review_round_settings_review_round_id');
        });


        // review_round_author_responses table
        Schema::create('review_author_responses', function (Blueprint $table) {
            $table->bigInteger('response_id')->autoIncrement()->comment('Primary key.');
            $table->bigInteger('review_round_id')->comment('ID of the review round.');
            $table->bigInteger('user_id')->comment('User ID of the assigned author participant that is submitting the response.');
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

            $table->index(['review_round_id'], 'review_author_responses_review_round_id');
            $table->index(['user_id'], 'review_author_responses_user_id');
            $table->index(['review_round_id', 'user_id'], 'review_author_responses_review_round_id_user_id');
        });

        Schema::create('review_author_response_settings', function (Blueprint $table) {
            $table->bigInteger('review_author_responses_setting_id')->autoIncrement();
            $table->bigInteger('response_id')->comment('ID of the review response this setting entry belongs to.');
            $table->string('locale', 28)->nullable();
            $table->string('setting_name');
            $table->text('setting_value')->nullable();

            $table->foreign('response_id')
                ->references('response_id')
                ->on('review_author_responses')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['response_id'], 'rars_review_author_response_id');
            $table->index(['review_author_response_setting_id'], 'rars_review_author_responses_setting_id');
        });

        Schema::create('review_author_response_authors', function (Blueprint $table) {
            $table->comment('Associates authors with responses submitted for review rounds(which are stored in review_author_responses).');

            $table->bigInteger('review_author_responses_author_id')->autoIncrement();
            $table->bigInteger('response_id')->comment('ID of the review round author response.');
            $table->bigInteger('author_id')->comment('ID of the author who the response was submitted on behalf of.');

            $table->foreign('response_id')
                ->references('response_id')
                ->on('review_author_responses')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('author_id')
                ->references('author_id')
                ->on('authors')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['response_id'], 'review_author_responses_authors_response_id');
            $table->index(['author_id'], 'review_author_responses_authors_user_id');
        });
    }

    public function down(): void
    {
        Schema::drop('review_round_settings');
        Schema::drop('review_author_response_settings');
        Schema::drop('review_author_response_authors');
        Schema::drop('review_author_responses');
    }
}
