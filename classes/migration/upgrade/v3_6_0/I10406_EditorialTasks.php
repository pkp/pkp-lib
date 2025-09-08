<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I10406_EditorialTasks.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10406_EditorialTasks.php
 *
 * @brief Adds migration for the editorial tasks and discussions
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I10406_EditorialTasks extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('queries', 'edit_tasks');
        Schema::rename('query_participants', 'edit_task_participants');
        Schema::table('edit_tasks', function (Blueprint $table) {
            $table->renameColumn('query_id', 'edit_task_id');
            $table->renameColumn('date_posted', 'created_at');
            $table->renameColumn('date_modified', 'updated_at');
            $table->dateTime('date_due')->nullable();
            $table->bigInteger('created_by')->nullable()->default(null);
            $table->unsignedSmallInteger('type')->default(1); // 1 - discussion, 2 - task
            $table->unsignedSmallInteger('status')->default(1); // record about the last activity, default EditorialTask:STATUS_NEW
            $table->renameIndex('queries_assoc_id', 'edit_tasks_assoc_id');
        });

        Schema::table('edit_tasks', function (Blueprint $table) {

            // Identify and set all users created a discussion
            DB::table('notes as n')
                ->select(['n.assoc_id', 'n.user_id', 'n.date_created'])
                ->joinSub(
                    // Get the first (latest) note for each query
                    DB::table('notes as nt')
                        ->select('nt.assoc_id')
                        ->selectRaw('MIN(nt.date_created) as min_date')
                        ->where('nt.assoc_type', '=', 0x010000a) // ASSOC_TYPE_QUERY
                        ->groupBy('nt.assoc_id'),
                    'agr',
                    fn (JoinClause $join) => $join->on('n.assoc_id', '=', 'agr.assoc_id')
                )
                ->whereColumn('n.date_created', 'agr.min_date')
                ->orderBy('n.assoc_id')
                ->each(function (object $note) {
                    DB::table('edit_tasks')
                        ->where('edit_task_id', $note->assoc_id)
                        ->update([
                            'created_by' => $note->user_id,
                            'created_at' => $note->date_created,
                            'updated_at' => $note->date_created,
                            'type' => 1, // discussion
                        ]);
                });
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::table('edit_task_participants', function (Blueprint $table) {
            $table->dropForeign('query_participants_user_id_foreign');
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();

            $table->renameColumn('query_participant_id', 'edit_task_participant_id');

            $table->dropForeign('query_participants_query_id_foreign');
            $table->renameColumn('query_id', 'edit_task_id');
            $table->foreign('edit_task_id')->references('edit_task_id')->on('edit_tasks')->cascadeOnDelete();

            $table->renameIndex('query_participants_unique', 'edit_task_participants_unique');
            $table->renameIndex('query_participants_query_id', 'edit_task_participants_edit_task_id');
            $table->renameIndex('query_participants_user_id', 'edit_task_participants_user_id');
            $table->boolean('is_responsible')->default(false);
        });

        Schema::create('edit_task_settings', function (Blueprint $table) {
            $table->comment('More data about editorial tasks, including localized properties such as the name.');
            $table->unsignedBigInteger('edit_task_setting_id')->autoIncrement()->primary();
            $table->bigInteger('edit_task_id');
            $table->foreign('edit_task_id')
                ->references('edit_task_id')
                ->on('edit_tasks')
                ->cascadeOnDelete();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['edit_task_id', 'locale', 'setting_name'], 'edit_task_settings_unique');
            $table->index(['edit_task_id'], 'edit_task_settings_edit_task_id');
        });

        Schema::create('edit_task_templates', function (Blueprint $table) {
            $table->comment('Represents templates for the editorial tasks.');
            $table->unsignedBigInteger('edit_task_template_id')->autoIncrement()->primary();
            $table->unsignedSmallInteger('stage_id');
            $table->boolean('include')->default(false);
            $table->bigInteger('email_template_id')->nullable();
            $table->foreign('email_template_id')
                ->references('email_id')
                ->on('email_templates');
            $table->timestamps();
        });

        Schema::create('edit_task_template_settings', function (Blueprint $table) {
            $table->comment('includes additional and multilingual data about the editorial task templates.');
            $table->id('edit_task_template_setting_id');
            $table->unsignedBigInteger('edit_task_template_id');
            $table->foreign('edit_task_template_id')
                ->references('edit_task_template_id')
                ->on('edit_task_templates')
                ->cascadeOnDelete();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['edit_task_template_id', 'locale', 'setting_name'], 'edit_task__template_settings_unique');
            $table->index(['edit_task_template_id'], 'edit_task_template_settings_edit_task_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
