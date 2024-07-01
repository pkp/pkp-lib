<?php

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