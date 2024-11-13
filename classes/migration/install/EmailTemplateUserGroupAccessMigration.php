<?php

/**
 * @file classes/migration/install/EmailTemplateUserGroupAccessMigration.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HighlightsMigration
 *
 * @brief Describe database table structures for email template user group access
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EmailTemplateUserGroupAccessMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contextDao = \APP\core\Application::getContextDAO();
        Schema::create('email_template_user_group_access', function (Blueprint $table) use ($contextDao) {
            $table->bigInteger('email_template_user_group_access_id')->autoIncrement()->comment('Primary key');
            $table->string('email_key', 255)->comment("The email template's unique key");
            $table->bigInteger('context_id')->comment('Identifier for the context for which the user group assignment occurs.');
            $table->bigInteger('user_group_id')->nullable()->comment('The user group ID.');

            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade')->onDelete('cascade');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('email_template_user_group_access');
    }
}
