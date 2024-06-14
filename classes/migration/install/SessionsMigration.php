<?php

/**
 * @file classes/migration/install/SessionsMigration.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SessionsMigration
 *
 * @brief Describe sessions database table.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SessionsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->comment('Session data for logged-in users.');
            $table->string('id')->primary();

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id', 'sessions_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'sessions_user_id');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('last_activity')->index();
            $table->longText('payload');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('sessions');
    }
}
