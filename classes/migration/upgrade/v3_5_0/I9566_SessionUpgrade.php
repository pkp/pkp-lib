<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9566_SessionUpgrade.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9566_SessionUpgrade
 *
 * @brief upgrade the session schema
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PKP\migration\Migration;

class I9566_SessionUpgrade extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('sessions');

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
    }
}
