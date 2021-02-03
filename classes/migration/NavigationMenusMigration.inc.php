<?php

/**
 * @file classes/migration/NavigationMenusMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class NavigationMenusMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// NavigationMenus
		Capsule::schema()->create('navigation_menus', function (Blueprint $table) {
			$table->bigInteger('navigation_menu_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->string('area_name', 255)->default('')->nullable();
			$table->string('title', 255);
		});

		// NavigationMenuItems
		Capsule::schema()->create('navigation_menu_items', function (Blueprint $table) {
			$table->bigInteger('navigation_menu_item_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->string('path', 255)->default('')->nullable();
			$table->string('type', 255)->default('')->nullable();
		});

		// Locale-specific navigation menu item data
		Capsule::schema()->create('navigation_menu_item_settings', function (Blueprint $table) {
			$table->bigInteger('navigation_menu_item_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->longText('setting_value')->nullable();
			$table->string('setting_type', 6);
			$table->index(['navigation_menu_item_id'], 'navigation_menu_item_settings_navigation_menu_id');
			$table->unique(['navigation_menu_item_id', 'locale', 'setting_name'], 'navigation_menu_item_settings_pkey');
		});

		// NavigationMenuItemAssignments which assign menu items to a menu and describe nested menu structure.
		Capsule::schema()->create('navigation_menu_item_assignments', function (Blueprint $table) {
			$table->bigInteger('navigation_menu_item_assignment_id')->autoIncrement();
			$table->bigInteger('navigation_menu_id');
			$table->bigInteger('navigation_menu_item_id');
			$table->bigInteger('parent_id')->nullable();
			$table->bigInteger('seq')->default(0)->nullable();
		});

		// Locale-specific navigation menu item assignments data
		Capsule::schema()->create('navigation_menu_item_assignment_settings', function (Blueprint $table) {
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
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('navigation_menu_item_assignment_settings');
		Capsule::schema()->drop('navigation_menu_item_assignments');
		Capsule::schema()->drop('navigation_menu_item_settings');
		Capsule::schema()->drop('navigation_menu_items');
		Capsule::schema()->drop('navigation_menus');
	}
}
