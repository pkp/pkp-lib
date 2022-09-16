<?php

/**
 * @file classes/migration/install/LogMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A log of all events associated with an object.
        Schema::create('event_log', function (Blueprint $table) {
            $table->bigInteger('log_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->datetime('date_logged');
            $table->bigInteger('event_type')->nullable();
            $table->text('message')->nullable();
            $table->smallInteger('is_translated')->nullable();
            $table->index(['assoc_type', 'assoc_id'], 'event_log_assoc');
        });

        // Event log associative data
        Schema::create('event_log_settings', function (Blueprint $table) {
            $table->bigInteger('log_id');
            $table->foreign('log_id', 'event_log_settings_log_id')->references('log_id')->on('event_log')->onDelete('cascade');

            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
            $table->unique(['log_id', 'setting_name'], 'event_log_settings_pkey');
        });

        // Add partial index (DBMS-specific)
        switch (DB::getDriverName()) {
            case 'mysql': DB::unprepared('CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name(50), setting_value(150))');
                break;
            case 'pgsql': DB::unprepared("CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name, setting_value) WHERE setting_name IN ('fileId', 'submissionId')");
                break;
        }

        // A log of all emails sent out related to an object.
        Schema::create('email_log', function (Blueprint $table) {
            $table->bigInteger('log_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
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
        Schema::create('email_log_users', function (Blueprint $table) {
            $table->bigInteger('email_log_id');
            $table->foreign('email_log_id')->references('log_id')->on('email_log')->onDelete('cascade');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->unique(['email_log_id', 'user_id'], 'email_log_user_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('email_log_users');
        Schema::drop('email_log');
        Schema::drop('event_log_settings');
        Schema::drop('event_log');
    }
}
