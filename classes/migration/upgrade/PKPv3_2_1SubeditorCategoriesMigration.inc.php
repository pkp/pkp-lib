<?php

/**
 * @file classes/migration/upgrade/PKPv3_2_1SubeditorCategoriesMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPv3_2_1SubeditorCategoriesMigration
 * @brief pkp/pkp-lib#5694 Allow subeditors to be assigned to Categories
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPv3_2_1SubeditorCategoriesMigration extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		// Schema changes
		Capsule::schema()->rename('section_editors', 'subeditor_submission_group');
		Capsule::schema()->table('subeditor_submission_group', function (Blueprint $table) {
			// Change section_id to assoc_type/assoc_id
			$table->bigInteger('assoc_type')->nullable(); // nullable see pkp/pkp-lib#7071
			$table->renameColumn('section_id', 'assoc_id');

			// Drop indexes
			$table->dropIndex('section_editors_pkey');
			$table->dropIndex('section_editors_context_id');
			$table->dropIndex('section_editors_section_id');
			$table->dropIndex('section_editors_user_id');

			// Create indexes
			$table->index(['context_id'], 'section_editors_context_id');
			$table->index(['assoc_id', 'assoc_type'], 'subeditor_submission_group_assoc_id');
			$table->index(['user_id'], 'subeditor_submission_group_user_id');
			$table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_pkey');
		});

		// Populate the assoc_type data in the newly created column
		Capsule::table('subeditor_submission_group')->update(['assoc_type' => ASSOC_TYPE_SECTION]);
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
		throw new PKP\install\DowngradeNotSupportedException();
	}
}
