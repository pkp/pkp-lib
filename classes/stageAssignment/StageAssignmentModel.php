<?php

/**
 * @defgroup stageAssignment Stage Assignment
 * Implements Stage Assignments, which describe the assignment of users to
 * stages (discrete parts of the workflow, e.g. Internal Review or Production).
 */

/**
 * @file classes/stageAssignment/StageAssignmentModel.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageAssignmentModel
 *
 * @ingroup stageAssignment
 *
 *
 * @brief Basic class describing a Stage Assignment.
 */

namespace PKP\stageAssignment;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use PKP\userGroup\relationships\UserGroupStage;

class StageAssignmentModel extends Model
{
    protected $table = 'stage_assignments';
    protected $primaryKey = 'stage_assignment_id';
    public $timestamps = false;

    protected $fillable = [
        'submissionId', 'userGroupId', 'userId', 
        'dateAssigned', 'recommendOnly', 'canChangeMetadata'
    ];

    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }

    protected function submissionId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['submission_id'],
            set: fn ($value) => ['submission_id' => $value],
        );
    }

    protected function userGroupId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['user_group_id'],
            set: fn ($value) => ['user_group_id' => $value],
        );
    }

    /**
     * Accessor and Mutator for User ID.
     */
    protected function userId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['user_id'],
            set: fn ($value) => ['user_id' => $value],
        );
    }

    protected function dateAssigned(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['date_assigned'],
            set: fn ($value) => ['date_assigned' => $value],
        );
    }

    protected function recommendOnly(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['recommend_only'],
            set: fn ($value) => ['recommend_only' => $value],
        );
    }

    protected function canChangeMetadata(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['can_change_metadata'],
            set: fn ($value) => ['can_change_metadata' => $value],
        );
    }

    // Relationships
    public function userGroupStage()
    {
        return $this->belongsTo(UserGroupStage::class, 'user_group_id', 'user_group_id');
    }

    protected function stageId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->userGroupStage?->stageId,
        );
    }

    // Scopes
    /**
     * Scope a query to only include stage assignments with a specific stage ID.
     */
    public function scopeWithStageId(Builder $query, int $stageId): Builder
    {
        return $query->whereHas('userGroupStage', function ($query) use ($stageId) {
            $query->where('stage_id', $stageId);
        });
    }

    /**
     * Scope a query to only include stage assignments with a specific submissionId.
     */
    public function scopeWithSubmissionId(Builder $query, int $submissionId): Builder
    {
        return $query->where('submission_id', $submissionId);
    }

    /**
     * Scope a query to only include stage assignments with a specific userGroupId.
     */
    public function scopeWithUserGroupId(Builder $query, int $userGroupId): Builder
    {
        return $query->where('user_group_id', $userGroupId);
    }

    /**
     * Scope a query to only include stage assignments with a specific userId.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include stage assignments with a specific userId.
     */
    public function scopeWithRecommendOnly(Builder $query, bool $recommendOnly): Builder
    {
        return $query->where('recommend_only', $recommendOnly);
    }

    /**
     * Scope a query to include stage assignments based on role IDs.
     * TODO: treat empty array and null $roleIds differently here - check filtering functions of EntityDAO
     */
    public function scopeWithRoleIds(Builder $query, array $roleIds): Builder
    {
        return $query->leftJoin('user_groups as ug', 'stage_assignments.user_group_id', '=', 'ug.user_group_id')
            ->when(!empty($roleIds), function ($query) use ($roleIds) {
                $query->whereIn('ug.role_id', $roleIds);
            });
        // return $query->leftJoin('user_groups as ug', 'stage_assignments.user_group_id', '=', 'ug.user_group_id')
        //     ->when($roleIds, function ($query) use ($roleIds) {
        //         $query->whereIn('ug.role_id', $roleIds);
        //     });
    }

    /**
    * Scope a query to only include stage assignments with specific submissionIds.
    */
    public function scopeWithSubmissionIds(Builder $query, array $submissionIds): Builder
    {
        return $query->whereIn('submission_id', $submissionIds);
    }

    public function userGroupStages(): HasMany
    {
        return $this->hasMany(UserGroupStage::class, 'user_group_id', 'user_group_id');
    }
}
