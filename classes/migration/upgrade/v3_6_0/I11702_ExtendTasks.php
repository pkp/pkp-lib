<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11702_ExtendTasks
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11702_ExtendTasks
 *
 * @brief Adds migration for the editorial tasks and discussions
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I11702_ExtendTasks extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('edit_tasks', function (Blueprint $table) {
            $table->removeColumn('status');
            $table->dateTime('date_started')->nullable();
            $table->dateTime('date_closed')->nullable();
            $table->string('title')->nullable();
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('edit_tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('status')->default(1);
            $table->removeColumn('date_started');
            $table->removeColumn('date_closed');
            $table->removeColumn('title');
        });
    }
}
