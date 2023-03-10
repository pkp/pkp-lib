<?php

/**
 * @file classes/migration/install/AnnouncementsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;

class AnnouncementsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Announcement types.
        Schema::create('announcement_types', function (Blueprint $table) {
            $table->comment('Announcement types allow for announcements to optionally be categorized.');
            $table->bigInteger('type_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'announcement_types_context_id');
        });

        // Locale-specific announcement type data
        Schema::create('announcement_type_settings', function (Blueprint $table) {
            $table->comment('More data about announcement types, including localized properties like their names.');
            $table->bigInteger('type_id');
            $table->foreign('type_id')->references('type_id')->on('announcement_types')->onDelete('cascade');
            $table->index(['type_id'], 'announcement_type_settings_type_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->unique(['type_id', 'locale', 'setting_name'], 'announcement_type_settings_pkey');
        });

        // Announcements.
        Schema::create('announcements', function (Blueprint $table) {
            $table->comment('Announcements are messages that can be presented to users e.g. on the homepage.');
            $table->bigInteger('announcement_id')->autoIncrement();
            //  NOT NULL not included for upgrade purposes
            $table->smallInteger('assoc_type')->nullable();
            $table->bigInteger('assoc_id');

            $table->bigInteger('type_id')->nullable();
            $table->foreign('type_id')->references('type_id')->on('announcement_types')->onDelete('set null');
            $table->index(['type_id'], 'announcements_type_id');

            $table->date('date_expire')->nullable();
            $table->datetime('date_posted');
            $table->index(['assoc_type', 'assoc_id'], 'announcements_assoc');
        });

        // Locale-specific announcement data
        Schema::create('announcement_settings', function (Blueprint $table) {
            $table->comment('More data about announcements, including localized properties like names and contents.');
            $table->bigInteger('announcement_id');
            $table->foreign('announcement_id')->references('announcement_id')->on('announcements')->onDelete('cascade');
            $table->index(['announcement_id'], 'announcement_settings_announcement_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->nullable();

            $table->unique(['announcement_id', 'locale', 'setting_name'], 'announcement_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('announcement_settings');
        Schema::drop('announcements');
        Schema::drop('announcement_type_settings');
        Schema::drop('announcement_types');
    }
}
