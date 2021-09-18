<?php

/**
 * @file classes/migration/install/NavigationMenusMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NavigationMenusMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // NavigationMenus
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->bigInteger('navigation_menu_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->string('area_name', 255)->default('')->nullable();
            $table->string('title', 255);
        });

        // NavigationMenuItems
        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->bigInteger('navigation_menu_item_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->string('path', 255)->default('')->nullable();
            $table->string('type', 255)->default('')->nullable();
        });

        // Locale-specific navigation menu item data
        Schema::create('navigation_menu_item_settings', function (Blueprint $table) {
            $table->bigInteger('navigation_menu_item_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->index(['navigation_menu_item_id'], 'navigation_menu_item_settings_navigation_menu_id');
            $table->unique(['navigation_menu_item_id', 'locale', 'setting_name'], 'navigation_menu_item_settings_pkey');
        });

        // NavigationMenuItemAssignments which assign menu items to a menu and describe nested menu structure.
        Schema::create('navigation_menu_item_assignments', function (Blueprint $table) {
            $table->bigInteger('navigation_menu_item_assignment_id')->autoIncrement();
            $table->bigInteger('navigation_menu_id');
            $table->bigInteger('navigation_menu_item_id');
            $table->bigInteger('parent_id')->nullable();
            $table->bigInteger('seq')->default(0)->nullable();
        });

        // Locale-specific navigation menu item assignments data
        Schema::create('navigation_menu_item_assignment_settings', function (Blueprint $table) {
            $table->bigInteger('navigation_menu_item_assignment_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->index(['navigation_menu_item_assignment_id'], 'assignment_settings_navigation_menu_item_assignment_id');
            $table->unique(['navigation_menu_item_assignment_id', 'locale', 'setting_name'], 'navigation_menu_item_assignment_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('navigation_menu_item_assignment_settings');
        Schema::drop('navigation_menu_item_assignments');
        Schema::drop('navigation_menu_item_settings');
        Schema::drop('navigation_menu_items');
        Schema::drop('navigation_menus');
    }
}
