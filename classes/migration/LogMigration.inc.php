<?php

/**
 * @file classes/migration/LogMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class LogMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// A log of all events associated with an object.
		Capsule::schema()->create('event_log', function (Blueprint $table) {
			$table->bigInteger('log_id')->autoIncrement();
			//  Nullable for upgrade purposes only 
			$table->bigInteger('assoc_type')->nullable();
			//  Nullable for upgrade purposes only 
			$table->bigInteger('assoc_id')->nullable();
			$table->bigInteger('user_id');
			$table->datetime('date_logged');
			$table->bigInteger('event_type')->nullable();
			$table->text('message')->nullable();
			$table->tinyInteger('is_translated')->nullable();
			$table->index(['assoc_type', 'assoc_id'], 'event_log_assoc');
		});

		// Event log associative data
		Capsule::schema()->create('event_log_settings', function (Blueprint $table) {
			$table->bigInteger('log_id');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
			$table->index(['log_id'], 'event_log_settings_log_id');
			$table->unique(['log_id', 'setting_name'], 'event_log_settings_pkey');
		});

		// A log of all emails sent out related to an object.
		Capsule::schema()->create('email_log', function (Blueprint $table) {
			$table->bigInteger('log_id')->autoIncrement();
			//  Nullable for upgrade purposes only 
			$table->bigInteger('assoc_type')->nullable();
			//  Nullable for upgrade purposes only 
			$table->bigInteger('assoc_id')->nullable();
			$table->bigInteger('sender_id');
			$table->datetime('date_sent');
			$table->bigInteger('event_type')->nullable();
			$table->string('from_address', 255)->nullable();
			$table->text('recipients')->nullable();
			$table->text('cc_recipients')->nullable();
			$table->text('bcc_recipients')->nullable();
			$table->string('subject', 255)->nullable();
			$table->text('body')->nullable();
			$table->index(['assoc_type', 'assoc_id'], 'email_log_assoc');
		});

		// Associations for email logs within a user.
		Capsule::schema()->create('email_log_users', function (Blueprint $table) {
			$table->bigInteger('email_log_id');
			$table->bigInteger('user_id');
			$table->unique(['email_log_id', 'user_id'], 'email_log_user_id');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('email_log_users');
		Capsule::schema()->drop('email_log');
		Capsule::schema()->drop('event_log_settings');
		Capsule::schema()->drop('event_log');
	}
}
