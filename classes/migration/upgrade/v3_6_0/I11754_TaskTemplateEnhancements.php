<?php


/**
 * @file classes/migration/upgrade/v3_6_0/I11754_TaskTemplateEnhancements.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
  
 * @class I11702_TaskTemplateEnhancements.php
 * @brief Add minimal enhancements for task templates.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I11754_TaskTemplateEnhancements extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('edit_tasks', 'edit_task_template_id')) {
            Schema::table('edit_tasks', function (Blueprint $table) {
                // if this task was auto-created from a template, keep the source template ID here. manual tasks keep NULL.
                $table->unsignedBigInteger('edit_task_template_id')
                      ->nullable()
                      ->comment('Source template ID if auto-created; NULL for manual tasks.');

                $table->index(['edit_task_template_id'], 'edit_tasks_edit_task_template_id');
            });
        }

        // template title
        if (Schema::hasTable('edit_task_templates') && !Schema::hasColumn('edit_task_templates', 'title')) {
            Schema::table('edit_task_templates', function (Blueprint $table) {
                $table->string('title', 255)->after('stage_id');
            });
        }

        // template and user_groups pivot
        if (!Schema::hasTable('edit_task_template_user_groups')) {
            Schema::create('edit_task_template_user_groups', function (Blueprint $table) {
                $table->comment('Links editorial task templates to user groups for auto-assignment.');
                $table->unsignedBigInteger('edit_task_template_id');
                $table->unsignedBigInteger('user_group_id');

                $table->primary(['edit_task_template_id', 'user_group_id'], 'ett_ug_pk');

                $table->foreign('edit_task_template_id', 'ett_ug_template_fk')
                    ->references('edit_task_template_id')->on('edit_task_templates')
                    ->onDelete('cascade');

                $table->foreign('user_group_id', 'ett_ug_user_group_fk')
                    ->references('user_group_id')->on('user_groups')
                    ->onDelete('cascade');

                $table->index(['user_group_id'], 'ett_ug_user_group_idx');
            });
        }
    }

    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
