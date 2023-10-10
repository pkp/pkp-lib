<?php

/**
 * @file classes/migration/install/CategoriesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoriesMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CategoriesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->comment('Categories permit the organization of submissions into a heirarchical structure.');
            $table->bigInteger('category_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'category_context_id');

            $table->bigInteger('parent_id')->nullable(); // Self-referential foreign key set below

            $table->bigInteger('seq')->nullable();
            $table->string('path', 255);
            $table->text('image')->nullable();

            $table->index(['context_id', 'parent_id'], 'category_context_parent_id');
            $table->unique(['context_id', 'path'], 'category_path');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('category_id')->on('categories')->onDelete('set null');
            $table->index(['parent_id'], 'category_parent_id');
        });

        // Category-specific settings
        Schema::create('category_settings', function (Blueprint $table) {
            $table->comment('More data about categories, including localized properties such as names.');
            $table->bigIncrements('category_setting_id');
            $table->bigInteger('category_id');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->index(['category_id'], 'category_settings_category_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['category_id', 'locale', 'setting_name'], 'category_settings_unique');
        });

        // Associations for categories and publications.
        Schema::create('publication_categories', function (Blueprint $table) {
            $table->comment('Associates publications (and thus submissions) with categories.');
            $table->bigIncrements('publication_category_id');
            $table->bigInteger('publication_id');
            $table->foreign('publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
            $table->index(['publication_id'], 'publication_categories_publication_id');

            $table->bigInteger('category_id');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->index(['category_id'], 'publication_categories_category_id');

            $table->unique(['publication_id', 'category_id'], 'publication_categories_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('categories');
        Schema::drop('category_settings');
        Schema::drop('publication_categories');
    }
}
