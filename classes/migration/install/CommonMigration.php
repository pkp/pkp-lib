<?php

/**
 * @file classes/migration/install/CommonMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommonMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommonMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->comment('Describes the installation and upgrade version history for the application and all installed plugins.');
            $table->bigIncrements('version_id');
            $table->integer('major')->default(0)->comment('Major component of version number, e.g. the 2 in OJS 2.3.8-0');
            $table->integer('minor')->default(0)->comment('Minor component of version number, e.g. the 3 in OJS 2.3.8-0');
            $table->integer('revision')->default(0)->comment('Revision component of version number, e.g. the 8 in OJS 2.3.8-0');
            $table->integer('build')->default(0)->comment('Build component of version number, e.g. the 0 in OJS 2.3.8-0');
            $table->datetime('date_installed');
            $table->smallInteger('current')->default(0)->comment('1 iff the version entry being described is currently active. This permits the table to store past installation history for forensic purposes.');
            $table->string('product_type', 30)->comment('Describes the type of product this row describes, e.g. "plugins.generic" (for a generic plugin) or "core" for the application itelf')->nullable();
            $table->string('product', 30)->comment('Uniquely identifies the product this version row describes, e.g. "ojs2" for OJS 2.x, "languageToggle" for the language toggle block plugin, etc.')->nullable();
            $table->string('product_class_name', 80)->comment('Specifies the class name associated with this product, for plugins, or the empty string where not applicable.')->nullable();
            $table->smallInteger('lazy_load')->default(0)->comment('1 iff the row describes a lazy-load plugin; 0 otherwise');
            $table->smallInteger('sitewide')->default(0)->comment('1 iff the row describes a site-wide plugin; 0 otherwise');
            $table->unique(['product_type', 'product', 'major', 'minor', 'revision', 'build'], 'versions_unique');
        });

        // Common site settings.
        Schema::create('site', function (Blueprint $table) {
            $table->comment('A singleton table describing basic information about the site.');
            $table->bigIncrements('site_id');
            $table->bigInteger('redirect')->default(0)->comment('If not 0, redirect to the specified journal/conference/... site.');
            $table->string('primary_locale', 14)->comment('Primary locale for the site.');
            $table->smallInteger('min_password_length')->default(6);
            $table->string('installed_locales', 1024)->default('en')->comment('Locales for which support has been installed.');
            $table->string('supported_locales', 1024)->comment('Locales supported by the site (for hosted journals/conferences/...).')->nullable();
            $table->string('original_style_file_name', 255)->nullable();
        });

        // Site settings.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->comment('More data about the site, including localized properties such as its name.');
            $table->bigIncrements('site_setting_id');
            $table->string('setting_name', 255);
            $table->string('locale', 14)->default('');
            $table->mediumText('setting_value')->nullable();
            $table->unique(['setting_name', 'locale'], 'site_settings_unique');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->comment('All registered users, including authentication data and profile data.');
            $table->bigInteger('user_id')->autoIncrement();
            $table->string('username', 32);
            $table->string('password', 255);
            $table->string('email', 255);
            $table->string('url', 2047)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mailing_address', 255)->nullable();
            $table->string('billing_address', 255)->nullable();
            $table->string('country', 90)->nullable();
            $table->string('locales', 255)->default('[]');
            $table->text('gossip')->nullable();
            $table->datetime('date_last_email')->nullable();
            $table->datetime('date_registered');
            $table->datetime('date_validated')->nullable();
            $table->datetime('date_last_login')->nullable();
            $table->smallInteger('must_change_password')->nullable();
            $table->bigInteger('auth_id')->nullable();
            $table->string('auth_str', 255)->nullable();
            $table->smallInteger('disabled')->default(0);
            $table->text('disabled_reason')->nullable();
            $table->smallInteger('inline_help')->nullable();
        });

        switch (DB::getDriverName()) {
            case 'mysql':
                Schema::table('users', function (Blueprint $table) {
                    $table->unique(['username'], 'users_username');
                    $table->unique(['email'], 'users_email');
                });
                break;
            case 'pgsql':
                DB::unprepared('CREATE UNIQUE INDEX users_username on users (LOWER(username));');
                DB::unprepared('CREATE UNIQUE INDEX users_email on users (LOWER(email));');
                break;
        }

        Schema::create('user_settings', function (Blueprint $table) {
            $table->comment('More data about users, including localized properties like their name and affiliation.');
            $table->bigIncrements('user_setting_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'user_settings_user_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['user_id', 'locale', 'setting_name'], 'user_settings_unique');
            $table->index(['setting_name', 'locale'], 'user_settings_locale_setting_name_index');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->comment('Session data for logged-in users.');
            $table->string('session_id', 128);

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id', 'sessions_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'sessions_user_id');

            $table->string('ip_address', 39);
            $table->string('user_agent', 255)->nullable();
            $table->bigInteger('created')->default(0);
            $table->bigInteger('last_used')->default(0);
            $table->smallInteger('remember')->default(0);
            $table->text('data');
            $table->string('domain', 255)->nullable();

            $table->unique(['session_id'], 'sessions_pkey');
        });

        Schema::create('access_keys', function (Blueprint $table) {
            $table->comment('Access keys are used to provide pseudo-login functionality for security-minimal tasks. Passkeys can be emailed directly to users, who can use them for a limited time in lieu of standard username and password.');
            $table->bigInteger('access_key_id')->autoIncrement();
            $table->string('context', 40);
            $table->string('key_hash', 40);

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'access_keys_user_id');

            $table->bigInteger('assoc_id')->nullable();
            $table->datetime('expiry_date');
            $table->index(['key_hash', 'user_id', 'context'], 'access_keys_hash');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->comment('User notifications created during certain operations.');
            $table->bigInteger('notification_id')->autoIncrement();

            $table->bigInteger('context_id')->nullable();
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'notifications_context_id');

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'notifications_user_id');

            $table->bigInteger('level');
            $table->bigInteger('type');
            $table->datetime('date_created');
            $table->datetime('date_read')->nullable();
            $table->bigInteger('assoc_type')->nullable();
            $table->bigInteger('assoc_id')->nullable();
            $table->index(['context_id', 'user_id', 'level'], 'notifications_context_id_user_id');
            $table->index(['context_id', 'level'], 'notifications_context_id_level');
            $table->index(['assoc_type', 'assoc_id'], 'notifications_assoc');
            $table->index(['user_id', 'level'], 'notifications_user_id_level');
        });

        // Stores metadata for specific notifications
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->comment('More data about notifications, including localized properties.');
            $table->bigIncrements('notification_setting_id');
            $table->bigInteger('notification_id');
            $table->foreign('notification_id')->references('notification_id')->on('notifications')->onDelete('cascade');
            $table->index(['notification_id'], 'notification_settings_notification_id');

            $table->string('locale', 14)->nullable();
            $table->string('setting_name', 64);
            $table->mediumText('setting_value');
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
            $table->unique(['notification_id', 'locale', 'setting_name'], 'notification_settings_unique');
        });

        Schema::create('notification_subscription_settings', function (Blueprint $table) {
            $table->comment('Which email notifications a user has chosen to unsubscribe from.');
            $table->bigInteger('setting_id')->autoIncrement();
            $table->string('setting_name', 64);
            $table->mediumText('setting_value');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'notification_subscription_settings_user_id');

            $table->bigInteger('context');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context'], 'notification_subscription_settings_context');

            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
        });

        Schema::create('email_templates_default_data', function (Blueprint $table) {
            $table->comment('Default email templates created for every installed locale.');
            $table->bigIncrements('email_templates_default_data_id');
            $table->string('email_key', 255)->comment('Unique identifier for this email.');
            $table->string('locale', 14)->default('en');
            $table->string('name', 255);
            $table->string('subject', 255);
            $table->text('body')->nullable();
            $table->unique(['email_key', 'locale'], 'email_templates_default_data_unique');
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->comment('Custom email templates created by each context, and overrides of the default templates.');
            $table->bigInteger('email_id')->autoIncrement();
            $table->string('email_key', 255)->comment('Unique identifier for this email.');

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'email_templates_context_id');

            $table->string('alternate_to', 255)->nullable();
            $table->index(['alternate_to'], 'email_templates_alternate_to');

            $table->unique(['email_key', 'context_id'], 'email_templates_email_key');
        });

        Schema::create('email_templates_settings', function (Blueprint $table) {
            $table->comment('More data about custom email templates, including localized properties such as the subject and body.');
            $table->bigIncrements('email_template_setting_id');
            $table->bigInteger('email_id');
            $table->foreign('email_id', 'email_templates_settings_email_id')->references('email_id')->on('email_templates')->onDelete('cascade');
            $table->index(['email_id'], 'email_templates_settings_email_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['email_id', 'locale', 'setting_name'], 'email_templates_settings_unique');
        });

        // Resumption tokens for the OAI protocol interface.
        Schema::create('oai_resumption_tokens', function (Blueprint $table) {
            $table->comment('OAI resumption tokens are used to allow for pagination of large result sets into manageable pieces.');
            $table->bigIncrements('oai_resumption_token_id');
            $table->string('token', 32);
            $table->bigInteger('expire');
            $table->integer('record_offset');
            $table->text('params')->nullable();
            $table->unique(['token'], 'oai_resumption_tokens_unique');
        });

        // Plugin settings.
        Schema::create('plugin_settings', function (Blueprint $table) {
            $table->comment('More data about plugins, including localized properties. This table is frequently used to store plugin-specific configuration.');
            $table->bigIncrements('plugin_setting_id');
            $table->string('plugin_name', 80);
            $table->bigInteger('context_id');
            $table->string('setting_name', 80);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
            $table->index(['plugin_name'], 'plugin_settings_plugin_name');
            $table->unique(['plugin_name', 'context_id', 'setting_name'], 'plugin_settings_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('plugin_settings');
        Schema::drop('oai_resumption_tokens');
        Schema::drop('email_templates_settings');
        Schema::drop('email_templates');
        Schema::drop('email_templates_default_data');
        Schema::drop('mailable_templates');
        Schema::drop('notification_subscription_settings');
        Schema::drop('notification_settings');
        Schema::drop('notifications');
        Schema::drop('access_keys');
        Schema::drop('sessions');
        Schema::drop('user_settings');
        Schema::drop('users');
        Schema::drop('site_settings');
        Schema::drop('site');
        Schema::drop('versions');
    }
}
