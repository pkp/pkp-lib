<?php

/**
 * @file classes/migration/upgrade/PKPv3_4_0UpgradeMigrationTEMP.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPv3_4_0UpgradeMigrationTEMP
 * @brief Temporary, describe upgrade/downgrade operations for those using main branch and 3.4.0.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPv3_4_0UpgradeMigrationTEMP extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		// pkp/pkp-lib#4904: additional column in the table usage_stats_temporary_records
        if (Capsule::schema()->hasTable('usage_stats_temporary_records') && !Capsule::schema()->hasColumn('usage_stats_temporary_records', 'representation_id')) {
            Capsule::schema()->table('usage_stats_temporary_records', function(Blueprint $table) {
                $table->bigInteger('representation_id')->nullable()->default(NULL);
            });            
        }
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
	}
}
