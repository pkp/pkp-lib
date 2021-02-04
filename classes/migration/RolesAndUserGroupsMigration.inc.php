<?php

/**
 * @file classes/migration/RolesAndUserGroupsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RolesAndUserGroupsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class RolesAndUserGroupsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// User groups for a context.
		Capsule::schema()->create('user_groups', function (Blueprint $table) {
			$table->bigInteger('user_group_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->bigInteger('role_id');
			$table->smallInteger('is_default')->default(0);
			$table->smallInteger('show_title')->default(1);
			$table->smallInteger('permit_self_registration')->default(0);
			$table->smallInteger('permit_metadata_edit')->default(0);
			$table->index(['user_group_id'], 'user_groups_user_group_id');
			$table->index(['context_id'], 'user_groups_context_id');
			$table->index(['role_id'], 'user_groups_role_id');
		});

		// User Group-specific settings
		Capsule::schema()->create('user_group_settings', function (Blueprint $table) {
			$table->bigInteger('user_group_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
			$table->unique(['user_group_id', 'locale', 'setting_name'], 'user_group_settings_pkey');
		});

		// User group assignments (mapping of user to user groups)
		Capsule::schema()->create('user_user_groups', function (Blueprint $table) {
			$table->bigInteger('user_group_id');
			$table->bigInteger('user_id');
			$table->index(['user_group_id'], 'user_user_groups_user_group_id');
			$table->index(['user_id'], 'user_user_groups_user_id');
			$table->unique(['user_group_id', 'user_id'], 'user_user_groups_pkey');
		});

		// User groups assignments to stages in the workflow
		Capsule::schema()->create('user_group_stage', function (Blueprint $table) {
			$table->bigInteger('context_id');
			$table->bigInteger('user_group_id');
			$table->bigInteger('stage_id');
			$table->index(['context_id'], 'user_group_stage_context_id');
			$table->index(['user_group_id'], 'user_group_stage_user_group_id');
			$table->index(['stage_id'], 'user_group_stage_stage_id');
			$table->unique(['context_id', 'user_group_id', 'stage_id'], 'user_group_stage_pkey');
		});

		// Stage Assignments
		Capsule::schema()->create('stage_assignments', function (Blueprint $table) {
			$table->bigInteger('stage_assignment_id')->autoIncrement();
			$table->bigInteger('submission_id');
			$table->bigInteger('user_group_id');
			$table->bigInteger('user_id');
			$table->datetime('date_assigned');
			$table->smallInteger('recommend_only')->default(0);
			$table->smallInteger('can_change_metadata')->default(0);
			$table->unique(['submission_id', 'user_group_id', 'user_id'], 'stage_assignment');
			$table->index(['submission_id'], 'stage_assignments_submission_id');
			$table->index(['user_group_id'], 'stage_assignments_user_group_id');
			$table->index(['user_id'], 'stage_assignments_user_id');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('stage_assignments');
		Capsule::schema()->drop('user_group_stage');
		Capsule::schema()->drop('user_user_groups');
		Capsule::schema()->drop('user_group_settings');
		Capsule::schema()->drop('user_groups');
	}
}
