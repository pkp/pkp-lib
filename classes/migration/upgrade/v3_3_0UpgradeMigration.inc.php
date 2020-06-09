<?php

/**
 * @file classes/migration/upgrade/v3_3_0UpgradeMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class v3_3_0UpgradeMigration extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		Capsule::schema()->table('submissions', function (Blueprint $table) {
			$table->dropColumn('locale');
			$table->dropColumn('section_id');
		});
		Capsule::schema()->table('authors', function (Blueprint $table) {
			$table->dropColumn('submission_id');
		});
		Capsule::schema()->table('author_settings', function (Blueprint $table) {
			$table->dropColumn('setting_type');
		});
		Capsule::schema()->table('announcements', function (Blueprint $table) {
			$table->date('date_expire')->change();
		});
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
		throw new Exception('Downgrade not supported.');
	}
}
