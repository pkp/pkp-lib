<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13213_TaskTemplateForeignConstraint.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13213_TaskTemplateForeignConstraint
 *
 * @brief Add missing foreign key from edit_tasks.edit_task_template_id to edit_task_templates.edit_task_template_id.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13213_TaskTemplateForeignConstraint extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        if (!$this->hasForeignKey('edit_tasks', 'edit_task_task_template_id_fk')) {

            // Get orphaned template IDs from edit_tasks that do not have a corresponding entry in edit_task_templates
            $orphanedTemplateIds = DB::table('edit_tasks as et')
                ->leftJoin('edit_task_templates as ett', 'et.edit_task_template_id', '=', 'ett.edit_task_template_id')
                ->whereNull('ett.edit_task_template_id')
                ->whereNotNull('et.edit_task_template_id')
                ->pluck('et.edit_task_template_id');

            // Set the ID to null for orphaned templates in edit_tasks to avoid foreign key constraint violation
            DB::table('edit_tasks')
                ->whereIn('edit_task_template_id', $orphanedTemplateIds)
                ->update(['edit_task_template_id' => null]);

            Schema::table('edit_tasks', function (Blueprint $table) {
                $table->foreign('edit_task_template_id')
                    ->references('edit_task_template_id')
                    ->on('edit_task_templates')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        if ($this->hasForeignKey('edit_tasks', 'edit_task_task_template_id_fk')) {
            Schema::table('edit_tasks', function (Blueprint $table) {
                $table->dropForeign('edit_task_task_template_id_fk');
            });
        }
    }
}
