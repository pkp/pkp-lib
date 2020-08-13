<?php

/**
 * @file classes/migration/utils/EnableConstraintsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnableConstraintsMigration
 * @brief Enable schema constraints.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class EnableConstraintsMigration extends Migration {
        /**
         * Run the migration.
         * @return void
         */
        public function up() {
		Capsule::schema()->enableForeignKeyConstraints();
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->disableForeignKeyConstraints();
	}
}
