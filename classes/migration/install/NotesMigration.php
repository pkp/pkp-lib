<?php

/**
 * @file classes/migration/install/NotesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotesMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NotesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->comment('Notes allow users to annotate associated entities, such as submissions.');
            $table->bigInteger('note_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'notes_user_id');

            $table->datetime('date_created');
            $table->datetime('date_modified')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('message_id', 255)->nullable();
            $table->text('contents')->nullable();

            $table->index(['assoc_type', 'assoc_id'], 'notes_assoc');
            $table->index(['message_id'], 'notes_message_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('notes');
    }
}
