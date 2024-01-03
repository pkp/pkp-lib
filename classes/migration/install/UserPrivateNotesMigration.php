<?php

/**
 * @file classes/migration/install/UserPrivateNotesMigration.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserPrivateNotesMigration
 *
 * @brief Describe database table structure for user private notes
 */

namespace PKP\migration\install;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;
use PKP\migration\Migration;

class UserPrivateNotesMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_private_notes', function (Blueprint $table) {
            $table->comment('User private notes are an addition to the gossip, but this one is private to each context.');
            $table->bigInteger('user_private_note_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');

            $table->bigInteger('user_id');
            $userDao = Repo::user()->dao;
            $table->foreign('user_id')->references($userDao->primaryKeyColumn)->on($userDao->table)->onDelete('cascade');

            $table->unique(['context_id', 'user_id'], 'user_private_notes_unique');
            $table->index(['context_id'], 'user_private_notes_context_id_foreign');

            $table->string('note')->default('');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('user_private_notes');
    }
}
