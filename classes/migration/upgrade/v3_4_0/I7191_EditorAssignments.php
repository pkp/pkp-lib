<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_EditorAssignments.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_EditorAssignments
 * @brief Update the subeditor_submission_group table to accomodate new editor assignment settings
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I7191_EditorAssignments extends \PKP\migration\Migration
{
    /**
     * Adds a user_group_id column to the subeditor_submission_group
     * table and adds initial data. Adds foreign keys where appropriate.
     */
    public function up(): void
    {
        if (empty($this->sectionDb) || empty($this->sectionIdColumn) || empty($this->contextColumn)) {
            throw new Exception('Upgrade could not be completed because required properties for the I7191_EditorAssignments migration are undefined.');
        }

        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->bigInteger('user_group_id')->nullable();
        });

        $this->setUserGroup();
        $this->deleteOrphanedAssignments();
        $this->addAutomatedAssignments();

        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->bigInteger('user_group_id')->nullable(false)->change();
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups');
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
            $table->dropForeign('user_group_id');
            $table->dropColumn('user_group_id');
        });
    }

    /**
     * Set the user group for all existing assignments
     *
     * Uses the default user group for Role::ROLE_ID_SUB_EDITOR
     * if one exists
     */
    protected function setUserGroup(): void
    {
        DB::table('user_groups')
            ->where('role_id', '=', 17) // Role::ROLE_ID_SUB_EDITOR
            ->orderBy('is_default')
            ->get(['user_group_id', 'context_id'])
            ->each(function ($row) {
                DB::table('subeditor_submission_group')
                    ->whereNull('user_group_id')
                    ->where('context_id', '=', $row->context_id)
                    ->update(['user_group_id' => $row->user_group_id]);
            });
    }

    /**
     * Delete any orphaned records
     *
     * 1. Users without a user group. They wouldn't have been
     *    assigned anyway so the cleanup should not impact
     *    existing workflows.
     * 2. Editorial assignments without a matching user record.
     */
    protected function deleteOrphanedAssignments(): void
    {
        DB::table('user_groups')
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
            $newRows = DB::table($this->sectionDb)
                ->where($this->contextColumn, '=', $userGroup->context_id)
                ->pluck($this->sectionIdColumn)
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
                DB::table('subeditor_submission_group')->insert($newRows->toArray());
            }
        });
    }
}
