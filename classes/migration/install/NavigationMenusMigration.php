<?php

/**
 * @file classes/migration/install/NavigationMenusMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use APP\core\Application;
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
            $table->comment('Navigation menus on the website are installed with the software as a default set, and can be customized.');
            $table->bigInteger('navigation_menu_id')->autoIncrement();
            $table->bigInteger('context_id')->nullable();
            $table->foreign('context_id', 'navigation_menus_context_id')->references(Application::getContextDAO()->primaryKeyColumn)->on(Application::getContextDAO()->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'navigation_menus_context_id');
            $table->string('area_name', 255)->default('')->nullable();
            $table->string('title', 255);
        });

        // NavigationMenuItems
        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->comment('Navigation menu items are single elements within a navigation menu.');
            $table->bigInteger('navigation_menu_item_id')->autoIncrement();
            $table->bigInteger('context_id')->nullable();
            $table->foreign('context_id', 'navigation_menu_items_context_id')->references(Application::getContextDAO()->primaryKeyColumn)->on(Application::getContextDAO()->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'navigation_menu_items_context_id');
            $table->string('path', 255)->default('')->nullable();
            $table->string('type', 255)->default('')->nullable();
        });

        // Locale-specific navigation menu item data
        Schema::create('navigation_menu_item_settings', function (Blueprint $table) {
            $table->comment('More data about navigation menu items, including localized content such as names.');
            $table->bigIncrements('navigation_menu_item_setting_id');
            $table->bigInteger('navigation_menu_item_id');
            $table->foreign('navigation_menu_item_id', 'navigation_menu_item_settings_navigation_menu_id')->references('navigation_menu_item_id')->on('navigation_menu_items')->onDelete('cascade');
            $table->index(['navigation_menu_item_id'], 'navigation_menu_item_settings_navigation_menu_item_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->unique(['navigation_menu_item_id', 'locale', 'setting_name'], 'navigation_menu_item_settings_unique');
        });

        // NavigationMenuItemAssignments which assign menu items to a menu and describe nested menu structure.
        Schema::create('navigation_menu_item_assignments', function (Blueprint $table) {
            $table->comment('Links navigation menu items to navigation menus.');
            $table->bigInteger('navigation_menu_item_assignment_id')->autoIncrement();

            $table->bigInteger('navigation_menu_id');
            $table->foreign('navigation_menu_id')->references('navigation_menu_id')->on('navigation_menus')->onDelete('cascade');
            $table->index(['navigation_menu_id'], 'navigation_menu_item_assignments_navigation_menu_id');

            $table->bigInteger('navigation_menu_item_id');
            $table->foreign('navigation_menu_item_id')->references('navigation_menu_item_id')->on('navigation_menu_items')->onDelete('cascade');
            $table->index(['navigation_menu_item_id'], 'navigation_menu_item_assignments_navigation_menu_item_id');

            $table->bigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'navigation_menu_item_assignments_parent_id')->references('navigation_menu_item_id')->on('navigation_menu_items')->onDelete('cascade');
            $table->index(['parent_id'], 'navigation_menu_item_assignments_parent_id');
            $table->bigInteger('seq')->default(0)->nullable();
        });

        // Locale-specific navigation menu item assignments data
        Schema::create('navigation_menu_item_assignment_settings', function (Blueprint $table) {
            $table->comment('More data about navigation menu item assignments to navigation menus, including localized content.');
            $table->bigIncrements('navigation_menu_item_assignment_setting_id');
            $table->bigInteger('navigation_menu_item_assignment_id');
            $table->foreign('navigation_menu_item_assignment_id', 'assignment_settings_navigation_menu_item_assignment_id')->references('navigation_menu_item_assignment_id')->on('navigation_menu_item_assignments')->onDelete('cascade');
            $table->index(['navigation_menu_item_assignment_id'], 'navigation_menu_item_assignment_settings_n_m_i_a_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->unique(['navigation_menu_item_assignment_id', 'locale', 'setting_name'], 'navigation_menu_item_assignment_settings_unique');
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
