<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9678_RemoveScheduledTasksTable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9678_RemoveScheduledTasksTable
 *
 * @brief Remove scheduled_tasks table.
 * @see https://github.com/pkp/pkp-lib/issues/9678
 * 
 */

namespace PKP\migration\upgrade\v3_5_0;

use PKP\migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I9678_RemoveScheduledTasksTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::drop('scheduled_tasks');
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->comment('The last time each scheduled task was run.');
            $table->bigIncrements('scheduled_task_id');
            $table->string('class_name', 255);
            $table->datetime('last_run')->nullable();
            $table->unique(['class_name'], 'scheduled_tasks_unique');
        });
    }
}
