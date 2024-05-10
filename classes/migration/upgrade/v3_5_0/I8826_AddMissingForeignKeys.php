<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I8826_AddMissingForeignKeys.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8826_AddMissingForeignKeys
 *
 * @brief Add foreign keys where missing.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I8826_AddMissingForeignKeys extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Permit NULL sender_id entries (previously used 0)
        Schema::table('email_log', function (Blueprint $table) {
            $table->bigInteger('sender_id')->nullable()->change();
        });

        // Add a new foreign key constraint and index on sender_id.
        Schema::table('email_log', function (Blueprint $table) {
            DB::table('email_log AS el')->leftJoin('users AS u', 'el.sender_id', '=', 'u.user_id')->whereNull('u.user_id')->update(['el.sender_id' => null]);
            $table->foreign('sender_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['sender_id'], 'email_log_sender_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
