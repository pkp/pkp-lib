<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_EditorAssignments.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_EditorAssignments
 *
 * @brief Update the subeditor_submission_group table to accomodate new editor assignment settings
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I7191_EditorAssignments extends \PKP\migration\Migration
{
    /**
     * Retrieves the name of the section table
     */
    protected abstract function getSectionTable(): string;

    /**
     * Retrieves the name of the section ID field
     */
    protected abstract function getSectionId(): string;

    /**
     * Retrieves the name of the context ID field
     */
    protected abstract function getContextId(): string;

    /**
     * Adds a user_group_id column to the subeditor_submission_group
     * table and adds initial data. Adds foreign keys where appropriate.
     */
    public function up(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->bigInteger('user_group_id')->nullable();
            // Drop the old unique index and introduce a new one with the user_group_id
            $table->dropUnique('section_editors_unique');
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id', 'user_group_id'], 'section_editors_unique');
        });

        $this->setUserGroup();
        $this->deleteOrphanedAssignments();
        $this->addAutomatedAssignments();

        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->bigInteger('user_group_id')->nullable(false)->change();
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
            $table->index(['user_group_id'], 'subeditor_submission_group_user_group_id');
        });
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->dropForeign('subeditor_submission_group_user_group_id_foreign');
            $table->dropColumn('user_group_id');
        });
    }

    /**
     * Set the user group for all existing assignments
     *
     * Uses the default user group for Role::ROLE_ID_SUB_EDITOR if one exists
     */
    protected function setUserGroup(): void
    {
        $bestUserGroupIdQuery = DB::table('user_groups', 'ug')
            ->whereColumn('ug.context_id', '=', 'ssg.context_id')
            ->whereRaw('ug.role_id = 17') // Role::ROLE_ID_SUB_EDITOR
            ->orderByDesc('ug.is_default')
            ->orderByDesc('ug.permit_metadata_edit')
            ->limit(1)
            ->select('ug.user_group_id');

        DB::table('subeditor_submission_group', 'ssg')
            ->whereNull('user_group_id')
            ->update(['user_group_id' => DB::raw("({$bestUserGroupIdQuery->toSql()})")]);
    }

    /**
     * Delete any orphaned records
     *
     * 1. Editorial assignments without a user group. They wouldn't have been
     *    assigned anyway so the cleanup should not impact existing workflows.
     * 2. Editorial assignments without a matching user record.
     */
    protected function deleteOrphanedAssignments(): void
    {
        DB::table('subeditor_submission_group as ssg')
            ->leftJoin('users as u', 'u.user_id', '=', 'ssg.user_id')
            ->whereNull('u.user_id')
            ->orWhereNull('ssg.user_group_id')
            ->delete();
    }

    /**
     * Add assignment records for any editors who would have
     * been automatically assigned in previous versions.
     *
     * When a context had only one user in a user group with
     * the following roles, that user would automatically be
     * assigned to every submission.
     *
     * Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT
     *
     * This migration assigns such users to every section in
     * the context
     */
    protected function addAutomatedAssignments(): void
    {
        $userCountQuery = fn (Builder $q) => $q->from('user_user_groups', 'uug')
            ->whereColumn('uug.user_group_id', '=', 'ug.user_group_id')
            ->selectRaw('COUNT(0)');

        $editorAssignmentsQuery = DB::table('user_groups', 'ug')
            // Look for role assignments at the WORKFLOW_STAGE_ID_SUBMISSION stage
            ->join('user_group_stage AS ugs', fn (JoinClause $j) => $j->on('ug.user_group_id', '=', 'ugs.user_group_id')
                ->whereColumn('ug.context_id', '=', 'ugs.context_id')
                ->where('ugs.stage_id', '=', 1) // WORKFLOW_STAGE_ID_SUBMISSION
            )
            // Single users with the roles Role::ROLE_ID_MANAGER and Role::ROLE_ID_ASSISTANT at the context
            ->join('user_user_groups AS uug', fn (JoinClause $j) => $j->on('uug.user_group_id', '=', 'ug.user_group_id')
                ->whereIn('ug.role_id', [16, 4097]) // [Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT]
                ->where($userCountQuery, '=', 1)
            )
            // Grabs all sections of the context
            ->join("{$this->getSectionTable()} AS s", "s.{$this->getContextId()}", '=', 'ug.context_id')
            // Only users that are not already assigned to the section
            ->leftJoin('subeditor_submission_group AS ssg', fn (JoinClause $j) => $j->on('ssg.context_id', '=', 'ug.context_id')
                ->whereColumn('ssg.assoc_id', '=', "s.{$this->getSectionId()}")
                ->where('ssg.assoc_type', '=', 530) // ASSOC_TYPE_SECTION
                ->whereColumn('ssg.user_id', '=', 'uug.user_id')
                ->whereColumn('ssg.user_group_id', '=', 'ug.user_group_id')
            )
            ->whereNull('ssg.user_id')
            ->get([
                'ug.context_id',
                DB::raw('530 AS assoc_type'), // ASSOC_TYPE_SECTION
                DB::raw("s.{$this->getSectionId()} AS assoc_id"),
                'uug.user_id',
                'ug.user_group_id'
            ])
            ->map(fn (object $row) => (array) $row)
            ->toArray();

        DB::table('subeditor_submission_group')->insert($editorAssignmentsQuery);
    }
}
