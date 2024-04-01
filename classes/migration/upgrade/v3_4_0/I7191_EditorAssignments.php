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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I7191_EditorAssignments extends \PKP\migration\Migration
{
    protected abstract function getSectionTable(): string;
    protected abstract function getSectionId(): string;
    protected abstract function getContextId(): string;

    /**
     * Adds a user_group_id column to the subeditor_submission_group
     * table and adds initial data. Adds foreign keys where appropriate.
     */
    public function up(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->bigInteger('user_group_id')->nullable();
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
            ->whereRaw('ug.role_id =  17') // Role::ROLE_ID_SUB_EDITOR
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
        DB::table('subeditor_submission_group')
            ->whereNull('user_group_id')
            ->delete();

        DB::table('subeditor_submission_group as ssg')
            ->leftJoin('users as u', 'u.user_id', '=', 'ssg.user_id')
            ->whereNull('u.user_id')
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
        $userGroups = DB::table('user_groups')
            ->whereIn('role_id', [16, 4097]) // [Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT]
            ->get(['user_group_id', 'context_id']);

        $userGroups->each(function ($userGroup) {
            $userIds = DB::table('user_user_groups')
                ->where('user_group_id', '=', $userGroup->user_group_id)
                ->pluck('user_id');
            if ($userIds->count() !== 1) {
                return;
            }
            $newRows = DB::table($this->getSectionTable())
                ->where($this->getContextId(), '=', $userGroup->context_id)
                ->pluck($this->getSectionId())
                ->map(function (int $sectionId) use ($userGroup, $userIds) {
                    return [
                        'context_id' => $userGroup->context_id,
                        'assoc_type' => 530, // ASSOC_TYPE_SECTION
                        'assoc_id' => $sectionId,
                        'user_id' => $userIds->first(),
                        'user_group_id' => $userGroup->user_group_id,
                    ];
                });
            if ($newRows->count()) {
                DB::table('subeditor_submission_group')->insertOrIgnore($newRows->toArray());
            }
        });
    }
}
