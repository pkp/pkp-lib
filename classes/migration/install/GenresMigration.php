<?php

/**
 * @file classes/migration/install/GenresMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenresMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\submission\Genre;

class GenresMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A context's submission file genres.
        Schema::create('genres', function (Blueprint $table) {
            $table->comment('The types of submission files configured for each context, such as Article Text, Data Set, Transcript, etc.');
            $table->bigInteger('genre_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'genres_context_id');

            $table->bigInteger('seq');
            $table->smallInteger('enabled')->default(1);

            $table->bigInteger('category')->default(Genre::GENRE_CATEGORY_DOCUMENT);

            $table->smallInteger('dependent')->default(0);
            $table->smallInteger('supplementary')->default(0);
            $table->smallInteger('required')->default(0)->comment('Whether or not at least one file of this genre is required for a new submission.');
            $table->string('entry_key', 30)->nullable();
        });

        // Genre settings
        Schema::create('genre_settings', function (Blueprint $table) {
            $table->comment('More data about file genres, including localized properties such as the genre name.');
            $table->bigIncrements('genre_setting_id');
            $table->bigInteger('genre_id');
            $table->foreign('genre_id')->references('genre_id')->on('genres')->onDelete('cascade');
            $table->index(['genre_id'], 'genre_settings_genre_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');

            $table->unique(['genre_id', 'locale', 'setting_name'], 'genre_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('genre_settings');
        Schema::drop('genres');
    }
}
