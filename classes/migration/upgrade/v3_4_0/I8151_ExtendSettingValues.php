<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8151_ExtendSettingValues.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8151_ExtendSettingValues
 * @brief Describe upgrade/downgrade operations for extending TEXT columns to MEDIUMTEXT
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I8151_ExtendSettingValues extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcement_type_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('announcement_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('category_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('user_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('notification_subscription_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('email_templates_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('plugin_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('controlled_vocab_entry_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('genre_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('library_file_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('event_log_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('citation_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('filter_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('navigation_menu_item_assignment_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('review_form_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('review_form_element_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('user_group_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('submission_file_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('author_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('data_object_tombstone_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This downgrade is intentionally not implemented. Changing MEDIUMTEXT back to TEXT
        // may result in data truncation. Having MEDIUMTEXT in place of TEXT in an otherwise
        // downgraded database will not have side-effects.
    }
}
