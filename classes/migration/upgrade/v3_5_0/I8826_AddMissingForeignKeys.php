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

use APP\core\Application;
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

        // Permit NULL context_id entries (previously used 0)
        Schema::table('user_groups', function (Blueprint $table) {
            $table->bigInteger('context_id')->nullable()->change();
        });

        // Add a new foreign key constraint and index on context_id.
        Schema::table('user_groups', function (Blueprint $table) {
            $contextDao = Application::getContextDAO();

            // context_id 0 gets changed to null
            DB::table('user_groups')->where('context_id', 0)->update(['context_id' => null]);
            // Otherwise, context_id not corresponding to a context gets deleted
            DB::table('user_groups AS ug')->leftJoin($contextDao->tableName . ' AS c', 'ug.context_id', '=', 'c.' . $contextDao->primaryKeyColumn)->whereNull('c.' . $contextDao->primaryKeyColumn)->whereNotNull('ug.context_id')->delete();

            // Add the foreign key constraint. (The index already exists.)
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
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
