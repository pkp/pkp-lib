<?php

/**
 * @file classes/migration/ViewsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ViewsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Tracking of views for various types of objects such as files, reviews, etc
		Capsule::schema()->create('item_views', function (Blueprint $table) {
			// pkp/pkp-lib#6093 Can't declare foreign key constraints on assoc_type/assoc_id pairs
			$table->bigInteger('assoc_type');
			$table->string('assoc_id', 32);

			$table->bigInteger('user_id')->nullable();
			$table->foreign('user_id')->references('user_id')->on('users');

			$table->datetime('date_last_viewed')->nullable();

			$table->unique(['assoc_type', 'assoc_id', 'user_id'], 'item_views_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('item_views');
	}
}
