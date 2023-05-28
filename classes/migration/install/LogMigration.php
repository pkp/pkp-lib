<?php

/**
 * @file classes/migration/install/LogMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogMigration
 *
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
        Schema::create('event_log', function (Blueprint $table) {
            $table->comment('A log of all events related to an object like a submission.');
            $table->bigInteger('log_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'event_log_user_id');

            $table->datetime('date_logged');
            $table->bigInteger('event_type')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_translated')->nullable();
            $table->index(['assoc_type', 'assoc_id'], 'event_log_assoc');
        });

        Schema::create('event_log_settings', function (Blueprint $table) {
            $table->comment('Data about an event log entry. This data is commonly used to display information about an event to a user.');
            $table->bigIncrements('event_log_setting_id');
            $table->bigInteger('log_id');
            $table->foreign('log_id', 'event_log_settings_log_id')->references('log_id')->on('event_log')->onDelete('cascade');
            $table->index(['log_id'], 'event_log_settings_log_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)')->nullable();
            $table->unique(['log_id', 'setting_name', 'locale'], 'event_log_settings_unique');
        });

        // Add partial index (DBMS-specific)
        switch (DB::getDriverName()) {
            case 'mysql': DB::unprepared('CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name(50), setting_value(150))');
                break;
            case 'pgsql': DB::unprepared("CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name, setting_value) WHERE setting_name IN ('fileId', 'submissionId')");
                break;
        }

        Schema::create('email_log', function (Blueprint $table) {
            $table->comment('A record of email messages that are sent in relation to an associated entity, such as a submission.');
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
            $table->comment('A record of users associated with an email log entry.');
            $table->bigIncrements('email_log_user_id');

            $table->bigInteger('email_log_id');
            $table->foreign('email_log_id')->references('log_id')->on('email_log')->onDelete('cascade');
            $table->index(['email_log_id'], 'email_log_users_email_log_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'email_log_users_user_id');

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
