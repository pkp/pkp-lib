<?php

/**
 * @file UserCommentsMigration.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommentsMigration
 *
 * @brief Migration to add table structures for user comments.
 */

namespace PKP\migration\install;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserCommentsMigration extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('user_comments', function (Blueprint $table) {
            $table->bigInteger('user_comment_id')->autoIncrement()->comment('Primary key.');
            $table->bigInteger('user_id')->comment('ID of the user that made the comment.');
            $table->bigInteger('context_id')->comment('ID of the context (e.g., journal) the comment belongs to.');
            $table->bigInteger('publication_id')->comment('ID of the publication that the comment belongs to.');
            $table->text('comment_text')->comment('The comment text.');
            $table->boolean('is_approved')->default(false)->comment('Boolean indicating if the comment is approved.');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['publication_id'], 'user_comments_publication_id');
            $table->index(['publication_id', 'context_id'], 'user_comments_publication_context');
            $table->index(['is_approved'], 'user_comments_is_approved');
        });

        Schema::create('user_comment_reports', function (Blueprint $table) {
            $table->bigInteger('user_comment_report_id')->autoIncrement()->comment('Primary key.');
            $table->bigInteger('user_comment_id')->comment('ID of the user comment that the reported was created for.');
            $table->bigInteger('user_id')->comment('ID of the user that made the report.');
            $table->text('note')->comment('Reason for the report.');
            $table->timestamps();

            $table->foreign('user_comment_id')
                ->references('user_comment_id')
                ->on('user_comments')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['user_comment_id'], 'user_comment_reports_user_comment_id');
        });

        Schema::create('user_comment_settings', function (Blueprint $table) {
            $table->bigInteger('user_comment_setting_id')->autoIncrement()->comment('Primary key.');
            $table->bigInteger('user_comment_id')->comment('ID of the user comment that the setting belongs to.');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->longText('setting_value')->nullable();

            $table->foreign('user_comment_id')
                ->references('user_comment_id')
                ->on('user_comments')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(['user_comment_id', 'locale', 'setting_name'], 'user_comment_settings_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('user_comment_reports');
        Schema::drop('user_comment_settings');
        Schema::drop('user_comments');
    }
}
