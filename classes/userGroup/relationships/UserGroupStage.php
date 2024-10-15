<?php

/**
 * @file classes/userGroup/relationships/UserGroupStage.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\relationships\UserGroupStage
 *
 * @brief UserGroupStage relationship metadata class.
 */

namespace PKP\userGroup\relationships;

use Illuminate\Database\Eloquent\Builder;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PKP\userGroup\UserGroup;


class UserGroupStage extends \Illuminate\Database\Eloquent\Model
{
    use HasCamelCasing;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = ['userGroupId', 'stageId', 'contextId'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_group_stage';


    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id', 'user_group_id');
    }

    public function scopeWithStageId(Builder $query, int $stageId): Builder
    {
        return $query->where('stage_id', $stageId);
    }

    public function scopeWithStageIds(Builder $query, array $stageIds): Builder
    {
        return $query->whereIn('stage_id', $stageIds);
    }

    public function scopeWithUserGroupId(Builder $query, int $userGroupId): Builder
    {
        return $query->where('user_group_id', $userGroupId);
    }

    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }
}
