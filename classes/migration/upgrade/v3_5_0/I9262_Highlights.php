<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9262_Highlights.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9262_Highlights
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9262_Highlights extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('highlights')) {
            Schema::create('highlights', function (Blueprint $table) {
                $table->comment('Highlights are featured items that can be presented to users, for example on the homepage.');
                $table->bigInteger('highlight_id')->autoIncrement();
                $table->bigInteger('context_id')->nullable();
                $contextDao = \APP\core\Application::getContextDAO();
                $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
                $table->index(['context_id'], 'highlights_context_id');

                $table->bigInteger('sequence');
                $table->string('url', 2047);
            });
        }

        if (!Schema::hasTable('highlight_settings')) {
            Schema::create('highlight_settings', function (Blueprint $table) {
                $table->comment('More data about highlights, including localized properties like title and description.');
                $table->bigIncrements('highlight_setting_id');
                $table->bigInteger('highlight_id');
                $table->foreign('highlight_id')->references('highlight_id')->on('highlights')->onDelete('cascade');
                $table->index(['highlight_id'], 'highlight_settings_highlight_id');

                $table->string('locale', 14)->default('');
                $table->string('setting_name', 255);
                $table->mediumText('setting_value')->nullable();

                $table->unique(['highlight_id', 'locale', 'setting_name'], 'highlight_settings_unique');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('highlight_settings');
        Schema::drop('highlights');
    }
}
