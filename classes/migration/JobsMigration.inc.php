<?php

/**
 * @file classes/migration/JobsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class JobsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		Capsule::schema()->create('jobs', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('queue');
			$table->longText('payload');
			$table->unsignedTinyInteger('attempts');
			$table->unsignedInteger('reserved_at')->nullable();
			$table->unsignedInteger('available_at');
			$table->unsignedInteger('created_at');

			$table->index(['queue', 'reserved_at']);
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('jobs');
	}
}
