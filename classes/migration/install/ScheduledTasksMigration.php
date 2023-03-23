<?php

/**
 * @file classes/migration/install/ScheduledTasksMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTasksMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ScheduledTasksMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The last run times of all scheduled tasks.
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->comment('The last time each scheduled task was run.');
            $table->bigIncrements('scheduled_task_id');
            $table->string('class_name', 255);
            $table->datetime('last_run')->nullable();
            $table->unique(['class_name'], 'scheduled_tasks_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('scheduled_tasks');
    }
}
