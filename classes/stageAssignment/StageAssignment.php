<?php

/**
 * @defgroup stageAssignment Stage Assignment
 * Implements Stage Assignments, which describe the assignment of users to
 * stages (discrete parts of the workflow, e.g. Internal Review or Production).
 */

/**
 * @file classes/stageAssignment/StageAssignment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageAssignment
 *
 * @ingroup stageAssignment
 *
 * @brief Basic class describing a Stage Assignment.
 */

namespace PKP\stageAssignment;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\userGroup\relationships\UserGroupStage;

class StageAssignment extends Model
{
    use HasCamelCasing;

    protected $table = 'stage_assignments';
    protected $primaryKey = 'stage_assignment_id';
    public $timestamps = false;

    protected $fillable = [
        'submissionId', 'userGroupId', 'userId',
        'dateAssigned', 'recommendOnly', 'canChangeMetadata'
    ];

    // Relationships

    /**
     * One to many relationship with user_group_stage table => UserGroupStage Eloquent Model
     *
     * To eagerly fill the userGroupStages Collection, the calling code should add
     * StageAssignment::with(['userGroupStages'])
     */
    public function userGroupStages(): HasMany
    {
        return $this->hasMany(UserGroupStage::class, 'user_group_id', 'user_group_id');
    }

    // Accessors and Mutators

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }


    // Scopes

    /**
     * Scope a query to only include stage assignments that are related
     * to userGroupStages having specific stageIds
     */
    public function scopeWithStageIds(Builder $query, ?array $stageIds): Builder
    {
        return $query->when($stageIds !== null && !empty($stageIds), function ($query) use ($stageIds) {
            return $query->whereHas('userGroupStages', function ($subQuery) use ($stageIds) {
                $subQuery->whereIn('stage_id', $stageIds);
            });
        });
    }

    /**
    * Scope a query to only include stage assignments with specific submissionIds.
    */
    public function scopeWithSubmissionIds(Builder $query, ?array $submissionIds): Builder
    {
        return $query->when($submissionIds !== null, function ($query) use ($submissionIds) {
            return $query->whereIn('stage_assignments.submission_id', $submissionIds);
        });
    }

    /**
    * Scope a query to only include stage assignments with a specific userGroupId.
    */
    public function scopeWithUserGroupId(Builder $query, ?int $userGroupId): Builder
    {
        return $query->when($userGroupId !== null, function ($query) use ($userGroupId) {
            return $query->where('user_group_id', $userGroupId);
        });
    }

    /**
    * Scope a query to only include stage assignments with a specific userId.
    */
    public function scopeWithUserId(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId !== null, function ($query) use ($userId) {
            return $query->where('user_id', $userId);
        });
    }

    /**
    * Scope a query to only include stage assignments with a specific userId.
    */
    public function scopeWithRecommendOnly(Builder $query, ?bool $recommendOnly): Builder
    {
        return $query->when($recommendOnly !== null, function ($query) use ($recommendOnly) {
            return $query->where('recommend_only', $recommendOnly);
        });
    }

    /**
    * Scope a query to include stage assignments based on role IDs.
    */
    public function scopeWithRoleIds(Builder $query, ?array $roleIds): Builder
    {
        return $query->when($roleIds !== null, function ($query) use ($roleIds) {
            $query->leftJoin('user_groups as ug', 'stage_assignments.user_group_id', '=', 'ug.user_group_id')
                ->whereIn('ug.role_id', $roleIds);
        });
    }

    /**
    * Scope a stageAssignment to only include stage assignments that are related to submissions having a specific contextId.
    */
    public function scopeWithContextId(Builder $query, ?int $contextId): Builder
    {
        return $query->when($contextId !== null, function ($query) use ($contextId) {
            return $query->join('submissions', 'stage_assignments.submission_id', '=', 'submissions.submission_id')
                ->where('submissions.context_id', $contextId);
        });
    }
}
