<?php

/**
 * @file classes/migration/install/ServersMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ServersMigration
 * @brief Describe database table structures.
 */

namespace APP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ServersMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Servers and basic server settings.
        Schema::create('servers', function (Blueprint $table) {
            $table->comment('A list of preprint servers managed by the installation.');
            $table->bigInteger('server_id')->autoIncrement();
            $table->string('path', 32);
            $table->float('seq', 8, 2)->default(0)->comment('Used to order lists of servers');
            $table->string('primary_locale', 14);
            $table->tinyInteger('enabled')->default(1)->comment('Controls whether or not the server is considered "live" and will appear on the website. (Note that disabled servers may still be accessible, but only if the user knows the URL.)');
            $table->unique(['path'], 'servers_path');
        });

        // Server settings.
        Schema::create('server_settings', function (Blueprint $table) {
            $table->comment('More data about server settings, including localized properties such as policies.');
            $table->bigIncrements('server_setting_id');

            $table->bigInteger('server_id');
            $table->foreign('server_id', 'server_settings_server_id')->references('server_id')->on('servers')->onDelete('cascade');
            $table->index(['server_id'], 'server_settings_server_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6)->nullable();

            $table->unique(['server_id', 'locale', 'setting_name'], 'server_settings_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('servers');
        Schema::drop('server_settings');
    }
}
