<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11917_TaskTemplateDueDate.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11917_TaskTemplateDueDate.php
 *
 * @brief Migrate task template to include due date functionality.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\editorialTask\enums\EditorialTaskDueInterval;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\migration\Migration;

class I11917_TaskTemplateDueDate extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->enum('due_interval', array_column(EditorialTaskDueInterval::cases(), 'value'))
                ->nullable()
                ->comment('Interval after which the task is due, from the time it is created.');
            $table->enum('type', array_column(EditorialTaskType::cases(), 'value'))->default(EditorialTaskType::DISCUSSION);
            $table->text('description')->nullable();
            $table->dropIndex('edit_task_templates_context_email_key_idx');
            $table->dropColumn('email_template_key');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->dropColumn('due_interval');
            $table->dropColumn('type');
            $table->dropColumn('description');
        });
    }
}
