<?php

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I11885_AddTypeToEditTaskTemplates extends Migration
{
    public function up(): void
    {
        // add column with default
        Schema::table('edit_task_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('edit_task_templates', 'type')) {
                $table->unsignedSmallInteger('type')->default(1);
            }
        });

        // backfill if any task exists with type=2 for a template, set that template to 2
        DB::table('edit_task_templates as ett')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('edit_tasks as et')
                  ->whereColumn('et.edit_task_template_id', 'ett.edit_task_template_id')
                  ->where('et.type', 2);
            })
            ->update(['type' => 2]);

        // index to speed filtering (context + type + stage)
        try {
            Schema::table('edit_task_templates', function (Blueprint $table) {
                $table->index(['context_id', 'type', 'stage_id'], 'ett_context_type_stage_idx');
            });
        } catch (\Throwable $e) {
            // index may already exist.. ignore
        }
    }

    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
