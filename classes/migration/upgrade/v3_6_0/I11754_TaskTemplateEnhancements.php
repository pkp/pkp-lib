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
use APP\core\Application;

class I11754_TaskTemplateEnhancements extends Migration
{
    public function up(): void
    {
        Schema::table('edit_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('edit_task_template_id')
                ->nullable()
                ->comment('Source template ID if auto-created; NULL for manual tasks.');
            $table->index(['edit_task_template_id'], 'edit_tasks_edit_task_template_id');
        });

        //title and context scoping to template
        $contextDao = Application::getContextDAO();
        Schema::table('edit_task_templates', function (Blueprint $table) use ($contextDao) {
            if (!Schema::hasColumn('edit_task_templates', 'title')) {
                $table->string('title', 255)->after('stage_id');
            }
            if (!Schema::hasColumn('edit_task_templates', 'context_id')) {
                $table->bigInteger('context_id')->comment('Journal ID for scoping templates');
                $table->index(['context_id'], 'edit_task_templates_context_id_idx');
                $table->foreign('context_id', 'edit_task_templates_context_fk')
                    ->references($contextDao->primaryKeyColumn)
                    ->on($contextDao->tableName)
                    ->onDelete('cascade');
            }
        });

        // pivot templates <-> user groups
        Schema::create('edit_task_template_user_groups', function (Blueprint $table) {
            $table->comment('Links editorial task templates to user groups for auto-assignment.');
            $table->unsignedBigInteger('edit_task_template_id');
            $table->bigInteger('user_group_id');

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

    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
